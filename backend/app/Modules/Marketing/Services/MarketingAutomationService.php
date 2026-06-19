<?php

namespace App\Modules\Marketing\Services;

use App\Models\SvpUser;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class MarketingAutomationService
{
    public const BATCH_PER_RULE = 40;

    public function __construct(
        protected MarketingSegmentService $segments,
        protected UserBotNotifyService $notify,
        protected SettingsStore $settings,
    ) {}

    /** @return array{processed:int, sent:int} */
    public function runCron(): array
    {
        if (! $this->settings->get('enabled', true)) {
            return ['processed' => 0, 'sent' => 0];
        }

        $stats = ['processed' => 0, 'sent' => 0];
        $owners = DB::table('svp_marketing_rules')
            ->where('enabled', true)
            ->distinct()
            ->pluck('owner_svp_user_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        foreach ($owners as $owner) {
            $part = $this->runForOwner($owner, self::BATCH_PER_RULE);
            $stats['processed'] += $part['processed'];
            $stats['sent'] += $part['sent'];
        }

        return $stats;
    }

    /** @return array{processed:int, sent:int} */
    public function runForOwner(int $ownerSvpUserId, int $limit = 40): array
    {
        $stats = ['processed' => 0, 'sent' => 0];
        $rules = DB::table('svp_marketing_rules')
            ->where('enabled', true)
            ->where('owner_svp_user_id', $ownerSvpUserId)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            foreach ($this->segments->eligibleUserIdsForRule($rule, $ownerSvpUserId, $limit) as $uid) {
                ++$stats['processed'];
                if ($this->issueAndSendForUser($rule, $uid)) {
                    ++$stats['sent'];
                }
            }
        }

        return $stats;
    }

    /** @return array{processed:int, sent:int} */
    public function runRuleNow(int $ruleId, int $limit = 80): array
    {
        $rule = DB::table('svp_marketing_rules')->where('id', $ruleId)->first();
        if (! $rule || ! $rule->enabled) {
            return ['processed' => 0, 'sent' => 0];
        }

        $stats = ['processed' => 0, 'sent' => 0];
        $owner = (int) ($rule->owner_svp_user_id ?? 0);
        foreach ($this->segments->eligibleUserIdsForRule($rule, $owner, $limit) as $uid) {
            ++$stats['processed'];
            if ($this->issueAndSendForUser($rule, $uid)) {
                ++$stats['sent'];
            }
        }

        return $stats;
    }

    /** @return array{ok:bool, offer_id?:int, message?:string} */
    public function sendManual(int $userId, int $ruleId, int $actorOwner): array
    {
        if ($userId < 1) {
            return ['ok' => false, 'message' => 'invalid_user'];
        }
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return ['ok' => false, 'message' => 'user_not_found'];
        }

        if ($ruleId > 0) {
            $rule = DB::table('svp_marketing_rules')->where('id', $ruleId)->first();
            if (! $rule) {
                return ['ok' => false, 'message' => 'rule_not_found'];
            }
            if ($actorOwner > 0 && (int) $rule->owner_svp_user_id !== $actorOwner) {
                return ['ok' => false, 'message' => 'forbidden'];
            }
        } else {
            $rule = (object) [
                'id' => 0,
                'owner_svp_user_id' => max(0, $actorOwner),
                'segment_key' => 'never_purchased',
                'cooldown_days' => 0,
                'discount_type' => 'percent',
                'discount_value' => 10,
                'code_valid_days' => 7,
                'max_uses_per_user' => 1,
                'message_body' => '',
                'channel_telegram' => true,
                'channel_bale' => true,
            ];
        }

        if (! $this->issueAndSendForUser($rule, $userId, true)) {
            return ['ok' => false, 'message' => 'send_failed'];
        }

        $offerId = (int) DB::table('svp_marketing_offers')
            ->where('svp_user_id', $userId)
            ->orderByDesc('id')
            ->value('id');

        return ['ok' => true, 'offer_id' => $offerId];
    }

    public function issueAndSendForUser(object $rule, int $userId, bool $forceManual = false): bool
    {
        $rid = (int) ($rule->id ?? 0);
        if ($userId < 1) {
            return false;
        }
        $user = SvpUser::query()->find($userId);
        if (! $user || (string) $user->status !== 'approved') {
            return false;
        }

        if ($rid > 0 && ! $forceManual) {
            $existing = DB::table('svp_marketing_offers')
                ->where('rule_id', $rid)
                ->where('svp_user_id', $userId)
                ->first();
            if ($existing && in_array((string) $existing->status, ['issued', 'sent', 'converted'], true)) {
                $cool = max(0, (int) ($rule->cooldown_days ?? 0));
                if ($cool > 0) {
                    $last = DB::table('svp_marketing_offers')
                        ->where('rule_id', $rid)
                        ->where('svp_user_id', $userId)
                        ->whereNotNull('sent_at')
                        ->orderByDesc('sent_at')
                        ->value('sent_at');
                    if ($last && strtotime((string) $last) > time() - $cool * 86400) {
                        return false;
                    }
                } elseif ($existing) {
                    return false;
                }
            }
        }

        $codeId = $this->ensureDiscountCode($rule, $userId);
        if ($codeId < 1) {
            return false;
        }

        $offerId = 0;
        if ($rid > 0) {
            $offerId = (int) (DB::table('svp_marketing_offers')
                ->where('rule_id', $rid)
                ->where('svp_user_id', $userId)
                ->value('id') ?? 0);
            if ($offerId < 1) {
                $offerId = (int) DB::table('svp_marketing_offers')->insertGetId([
                    'rule_id' => $rid,
                    'svp_user_id' => $userId,
                    'discount_code_id' => $codeId,
                    'status' => 'issued',
                    'meta_json' => json_encode(['segment' => (string) ($rule->segment_key ?? '')]),
                    'created_at' => now(),
                ]);
            } else {
                DB::table('svp_marketing_offers')->where('id', $offerId)->update([
                    'discount_code_id' => $codeId,
                    'status' => 'issued',
                ]);
            }
        } else {
            $offerId = (int) DB::table('svp_marketing_offers')->insertGetId([
                'rule_id' => 0,
                'svp_user_id' => $userId,
                'discount_code_id' => $codeId,
                'status' => 'issued',
                'meta_json' => json_encode(['manual' => 1]),
                'created_at' => now(),
            ]);
        }

        $codeRow = DB::table('svp_discount_codes')->where('id', $codeId)->first();
        $text = $this->buildMessage($rule, $user, $codeRow, $offerId);
        $channel = $this->channelForRule($rule);
        $owner = (int) ($rule->owner_svp_user_id ?? 0);

        $this->notify->sendToUser($user, $text, $channel, $owner);

        DB::table('svp_marketing_offers')->where('id', $offerId)->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        return true;
    }

    protected function ensureDiscountCode(object $rule, int $userId): int
    {
        $owner = max(0, (int) ($rule->owner_svp_user_id ?? 0));
        $seg = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($rule->segment_key ?? 'mkt'))) ?: 'mkt';
        $prefix = strtoupper(substr($seg, 0, 3));
        $code = $prefix.$userId.'-'.strtoupper(substr(md5("{$owner}:{$userId}:".(int) ($rule->id ?? 0)), 0, 6));

        $existing = DB::table('svp_discount_codes')
            ->where('owner_svp_user_id', $owner)
            ->where('code', $code)
            ->first();
        if ($existing) {
            return (int) $existing->id;
        }

        $days = max(1, (int) ($rule->code_valid_days ?? 7));
        $segKey = (string) ($rule->segment_key ?? '');

        return (int) DB::table('svp_discount_codes')->insertGetId([
            'owner_svp_user_id' => $owner,
            'code' => $code,
            'active' => true,
            'discount_type' => in_array((string) ($rule->discount_type ?? 'percent'), ['percent', 'fixed_toman'], true)
                ? (string) $rule->discount_type : 'percent',
            'discount_value' => max(0, (float) ($rule->discount_value ?? 0)),
            'max_uses' => max(1, (int) ($rule->max_uses_per_user ?? 1)),
            'valid_until' => now()->addDays($days),
            'restricted_svp_user_id' => $userId,
            'max_discount_toman' => $rule->max_discount_toman ?? null,
            'allow_new_purchase' => true,
            'allow_renew_same' => $segKey === 'expiring_renew',
            'allow_add_volume' => true,
            'allow_add_user_slots' => false,
            'created_at' => now(),
        ]);
    }

    protected function buildMessage(object $rule, SvpUser $user, ?object $codeRow, int $offerId): string
    {
        $body = trim((string) ($rule->message_body ?? ''));
        $code = $codeRow ? (string) $codeRow->code : '';
        if ($body === '') {
            $body = 'سلام {name}! پیشنهاد ویژه — کد: {code}';
        }

        return str_replace(
            ['{code}', '{name}', '{offer_id}'],
            [$code, trim((string) ($user->first_name ?? '')), (string) $offerId],
            $body
        );
    }

    protected function channelForRule(object $rule): string
    {
        $tg = ! isset($rule->channel_telegram) || $rule->channel_telegram;
        $bl = ! isset($rule->channel_bale) || $rule->channel_bale;
        if ($tg && $bl) {
            return 'both';
        }
        if ($bl) {
            return 'bale';
        }

        return 'telegram';
    }
}
