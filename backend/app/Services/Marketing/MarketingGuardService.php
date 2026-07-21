<?php

namespace App\Services\Marketing;

use App\Models\SvpMarketingOffer;
use App\Models\SvpUser;
use App\Services\NotificationDedupService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MarketingGuardService
{
    public const LAST_CRON_OPTION = 'simplevpbot_marketing_last_cron_run';

    public function __construct(
        protected SettingsStore $settings,
        protected NotificationDedupService $dedup,
    ) {}

    public function lifecycleConfirmed(): bool
    {
        return (bool) $this->settings->get('marketing_lifecycle_confirmed', false);
    }

    public function maxMessagesPerUserDay(): int
    {
        $n = (int) $this->settings->get('marketing_max_messages_per_user_day', 1);

        return max(1, min(3, $n));
    }

    public function usageHighPercent(): int
    {
        $n = (int) $this->settings->get('marketing_usage_high_percent', 75);

        return max(50, min(95, $n));
    }

    public function cronBlockReason(): string
    {
        if (! $this->settings->get('enabled', true)) {
            return 'bot_disabled';
        }
        if ($this->settings->get('crisis_mode', false)) {
            return 'crisis_mode';
        }
        if ($this->settings->get('suppress_bulk_user_notifications', false)) {
            return 'suppress_bulk';
        }
        if (! $this->lifecycleConfirmed()) {
            return 'awaiting_admin_confirm';
        }

        return '';
    }

    /**
     * @return array{ok:bool, reason?:string}
     */
    public function canSendToUser(?object $user, ?object $rule, bool $forceManual = false): array
    {
        if (! $user) {
            return ['ok' => false, 'reason' => 'invalid_user'];
        }
        if ((string) ($user->status ?? '') !== 'approved') {
            return ['ok' => false, 'reason' => 'user_not_approved'];
        }
        $uid = (int) ($user->id ?? 0);
        if ($uid < 1) {
            return ['ok' => false, 'reason' => 'invalid_user'];
        }
        $seg = (string) ($rule->segment_key ?? '');
        if ($seg === 'churned' && ! $this->settings->get('notify_idle_enabled', false)) {
            return ['ok' => false, 'reason' => 'idle_disabled'];
        }
        if (! $forceManual) {
            $block = $this->cronBlockReason();
            if ($block !== '') {
                return ['ok' => false, 'reason' => $block];
            }
        }
        $chTg = ! isset($rule->channel_telegram) || ! empty($rule->channel_telegram);
        $chBl = ! isset($rule->channel_bale) || ! empty($rule->channel_bale);
        if (! $chTg && ! $chBl) {
            return ['ok' => false, 'reason' => 'no_channel'];
        }
        if (! $forceManual) {
            if ($this->userDailyCapReached($uid)) {
                return ['ok' => false, 'reason' => 'daily_cap'];
            }
            $bucket = 'u'.$uid.':'.$seg;
            if (! $this->dedup->claim('marketing', $bucket, 30)) {
                return ['ok' => false, 'reason' => 'dedup'];
            }
            if ($seg === 'expiring_renew' && $this->expiryCronRecentForUser($uid)) {
                return ['ok' => false, 'reason' => 'expiry_cron_recent'];
            }
            $open = SvpMarketingOffer::query()
                ->where('svp_user_id', $uid)
                ->whereIn('status', ['issued', 'sent'])
                ->orderByDesc('id')
                ->first();
            if ($open && $open->discount_code_id) {
                $codeRow = DB::table('svp_discount_codes')->where('id', (int) $open->discount_code_id)->first();
                if ($codeRow && $this->discountCodeStillValid($codeRow)) {
                    return ['ok' => false, 'reason' => 'open_offer'];
                }
            }
        }

        return ['ok' => true];
    }

    public function userDailyCapReached(int $userId): bool
    {
        if ($userId < 1 || ! Schema::hasTable('svp_marketing_offers')) {
            return true;
        }
        $since = now()->subDay();
        $cnt = SvpMarketingOffer::query()
            ->where('svp_user_id', $userId)
            ->where('status', 'sent')
            ->where('sent_at', '>=', $since)
            ->count();

        return $cnt >= $this->maxMessagesPerUserDay();
    }

    public function discountCodeStillValid(?object $codeRow): bool
    {
        if (! $codeRow || empty($codeRow->active)) {
            return false;
        }
        $vu = (string) ($codeRow->valid_until ?? '');
        if ($vu !== '' && strtotime($vu.' UTC') < time()) {
            return false;
        }

        return true;
    }

    public function expiryCronRecentForUser(int $userId): bool
    {
        if (! Schema::hasTable('svp_services')) {
            return false;
        }
        $svcIds = DB::table('svp_services')
            ->where('user_id', $userId)
            ->whereNull('deleted_at')
            ->limit(20)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
        if ($svcIds === []) {
            return false;
        }
        $sent = (array) $this->settings->get('simplevpbot_expiry_sent_buckets', []);
        $cut = time() - 2 * 86400;
        foreach ($svcIds as $sid) {
            if ($sid < 1) {
                continue;
            }
            foreach ($sent as $bucket => $ts) {
                if (! is_string($bucket) || (int) $ts < $cut) {
                    continue;
                }
                if (str_contains($bucket, 'svc'.$sid.':')) {
                    return true;
                }
            }
        }

        return false;
    }

    public function recordSkip(object $rule, int $userId, string $reason): void
    {
        $uid = $userId;
        $rid = (int) ($rule->id ?? 0);
        if ($uid < 1 || $rid < 1 || ! Schema::hasTable('svp_marketing_offers')) {
            return;
        }
        $existing = SvpMarketingOffer::query()->where('rule_id', $rid)->where('svp_user_id', $uid)->first();
        $meta = [
            'segment' => (string) ($rule->segment_key ?? ''),
            'skip_reason' => preg_replace('/[^a-z0-9_]/', '', strtolower($reason)) ?: 'unknown',
        ];
        if ($existing) {
            $existing->update([
                'status' => 'skipped',
                'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
            ]);

            return;
        }
        SvpMarketingOffer::query()->create([
            'rule_id' => $rid,
            'svp_user_id' => $uid,
            'discount_code_id' => 0,
            'status' => 'skipped',
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /** @param  array{processed?:int,sent?:int,skipped?:int}  $stats */
    public function touchLastCronRun(array $stats): void
    {
        $this->settings->set(self::LAST_CRON_OPTION, [
            'at' => time(),
            'processed' => (int) ($stats['processed'] ?? 0),
            'sent' => (int) ($stats['sent'] ?? 0),
            'skipped' => (int) ($stats['skipped'] ?? 0),
        ]);
    }

    /** @return array<string, mixed> */
    public function lastCronRun(): array
    {
        $raw = $this->settings->get(self::LAST_CRON_OPTION, []);

        return is_array($raw) ? $raw : [];
    }

    public function expireStaleOffers(): void
    {
        if (! Schema::hasTable('svp_marketing_offers') || ! Schema::hasTable('svp_discount_codes')) {
            return;
        }
        $rows = DB::table('svp_marketing_offers as o')
            ->join('svp_discount_codes as d', 'd.id', '=', 'o.discount_code_id')
            ->whereIn('o.status', ['issued', 'sent'])
            ->whereNotNull('d.valid_until')
            ->where('d.valid_until', '<', now())
            ->limit(200)
            ->pluck('o.id');
        foreach ($rows as $id) {
            DB::table('svp_marketing_offers')->where('id', (int) $id)->update(['status' => 'expired']);
        }
    }

    /** @return array<string, mixed> */
    public function lifecycleStatsStub(int $windowDays = 30, int $ownerId = 0): array
    {
        return $this->lifecycleStats($windowDays, $ownerId);
    }

    /** @return array<string, mixed> */
    public function lifecycleStats(int $windowDays = 30, int $ownerId = 0): array
    {
        return app(MarketingLifecycleAnalyticsService::class)
            ->buildDashboardPayload($windowDays, $ownerId, $ownerId < 1);
    }
}
