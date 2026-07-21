<?php

namespace App\Modules\Marketing\Services;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotCheckoutUiService;
use App\Modules\Core\Bot\Services\BotServiceCheckoutService;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Services\Marketing\MarketingGuardService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class MarketingAutomationService
{
    public const BATCH_PER_RULE = 40;

    public function __construct(
        protected MarketingSegmentService $segments,
        protected UserBotNotifyService $notify,
        protected SettingsStore $settings,
        protected MarketingGuardService $guard,
        protected TextService $texts,
    ) {}

    /** @return array{processed:int, sent:int} */
    public function runCron(): array
    {
        $this->guard->expireStaleOffers();
        if ($this->guard->cronBlockReason() !== '') {
            $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0];
            $this->guard->touchLastCronRun($stats);

            return ['processed' => 0, 'sent' => 0];
        }

        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0];
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
            $stats['skipped'] += $part['skipped'] ?? 0;
        }

        $this->guard->touchLastCronRun($stats);

        return ['processed' => $stats['processed'], 'sent' => $stats['sent']];
    }

    /** @return array{processed:int, sent:int, skipped:int} */
    public function runForOwner(int $ownerSvpUserId, int $limit = 40): array
    {
        $stats = ['processed' => 0, 'sent' => 0, 'skipped' => 0];
        $rules = DB::table('svp_marketing_rules')
            ->where('enabled', true)
            ->where('owner_svp_user_id', $ownerSvpUserId)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            foreach ($this->segments->eligibleUserIdsForRule($rule, $ownerSvpUserId, $limit) as $uid) {
                ++$stats['processed'];
                $user = SvpUser::query()->find($uid);
                $check = $this->guard->canSendToUser($user, $rule, false);
                if (! ($check['ok'] ?? false)) {
                    ++$stats['skipped'];
                    if ($user && (int) ($rule->id ?? 0) > 0) {
                        $this->guard->recordSkip($rule, $uid, (string) ($check['reason'] ?? 'blocked'));
                    }
                    continue;
                }
                if ($this->issueAndSendForUser($rule, $uid)) {
                    ++$stats['sent'];
                } else {
                    ++$stats['skipped'];
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

        $extra = [];
        $kb = $this->offerCtaReplyMarkup($user, $offerId, $rule, $this->marketingContextForUser($userId, $rule));
        if ($kb !== null) {
            $extra['reply_markup'] = $kb;
        }
        $this->notify->sendToUser($user, $text, $channel, $owner, $extra);

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

    /** Enable owner=0 lifecycle rules and mark confirmed (WP confirm_lifecycle_defaults). */
    public function confirmLifecycleDefaults(): int
    {
        DB::table('svp_marketing_rules')
            ->where('owner_svp_user_id', 0)
            ->update(['enabled' => true, 'updated_at' => now()]);

        $this->settings->set('marketing_lifecycle_confirmed', true);

        return (int) DB::table('svp_marketing_rules')
            ->where('owner_svp_user_id', 0)
            ->where('enabled', true)
            ->count();
    }

    /**
     * Preview merged marketing message for a rule (WP Marketing_Automation::preview_message).
     *
     * @return array{ok:bool, message?:string, code?:string}
     */
    public function previewMessage(int $ruleId, int $userId = 0): array
    {
        if ($ruleId < 1) {
            return ['ok' => false, 'message' => 'invalid_rule'];
        }
        $rule = DB::table('svp_marketing_rules')->where('id', $ruleId)->first();
        if (! $rule) {
            return ['ok' => false, 'message' => 'rule_not_found'];
        }

        $user = $userId > 0 ? SvpUser::query()->find($userId) : null;
        if (! $user) {
            $user = new SvpUser([
                'id' => 0,
                'first_name' => 'مشتری',
                'status' => 'approved',
            ]);
        }

        $codeRow = (object) [
            'code' => 'PREVIEW-CODE',
            'discount_type' => (string) ($rule->discount_type ?? 'percent'),
            'discount_value' => (float) ($rule->discount_value ?? 10),
            'max_discount_toman' => $rule->max_discount_toman ?? null,
            'valid_until' => now()->addDays(3)->toDateTimeString(),
        ];

        $text = $this->buildMessage($rule, $user, $codeRow, 0);

        return [
            'ok' => true,
            'message' => $text,
            'code' => 'PREVIEW-CODE',
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{inline_keyboard: list<list<array<string, string>>>}|null
     */
    protected function offerCtaReplyMarkup(SvpUser $user, int $offerId, object $rule, array $ctx = []): ?array
    {
        if ($offerId < 1) {
            return null;
        }
        $cta = $this->resolveOfferCta($rule, $user, $ctx);
        $buttonKey = (string) ($cta['button_key'] ?? 'btn.marketing.use_offer');

        return [
            'inline_keyboard' => [[
                [
                    'text' => $this->texts->getForUser($buttonKey, $user, 'Use offer'),
                    'callback_data' => 'mkt_offer_cta:'.$offerId,
                ],
            ]],
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array{action: string, button_key: string, plan_id: int, service_id: int}
     */
    public function resolveOfferCta(object $rule, ?SvpUser $user = null, array $ctx = []): array
    {
        $seg = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($rule->segment_key ?? ''))) ?: '';
        $serviceId = max(0, (int) ($ctx['active_service_id'] ?? 0));
        $planId = max(0, (int) ($ctx['suggested_plan_id'] ?? 0));
        $out = [
            'action' => 'smart_checkout',
            'button_key' => 'btn.marketing.use_offer',
            'plan_id' => 0,
            'service_id' => $serviceId,
        ];
        switch ($seg) {
            case 'abandoned_checkout':
            case 'stale_buy_funnel':
                $out['button_key'] = 'btn.marketing.complete_purchase';
                break;
            case 'churned':
            case 'never_purchased':
                $out['action'] = 'start_buy';
                $out['button_key'] = 'btn.marketing.start_purchase';
                break;
            case 'upgrade_candidate':
                $out['action'] = 'start_buy';
                $out['button_key'] = 'btn.marketing.buy_suggested_plan';
                $out['plan_id'] = $planId;
                break;
            case 'expiring_renew':
                $out['action'] = 'renew_same';
                $out['button_key'] = 'btn.marketing.renew_with_code';
                break;
            case 'volume_boost':
                $out['action'] = 'add_volume';
                $out['button_key'] = 'btn.marketing.add_volume';
                break;
        }

        return $out;
    }

    /** @return array{active_service_id: int, suggested_plan_id: int} */
    protected function marketingContextForUser(int $userId, object $rule): array
    {
        $activeServiceId = 0;
        if ($userId > 0) {
            $activeServiceId = (int) (SvpService::query()
                ->where('user_id', $userId)
                ->whereNull('deleted_at')
                ->where('status', 'active')
                ->orderByDesc('id')
                ->value('id') ?? 0);
        }

        return [
            'active_service_id' => $activeServiceId,
            'suggested_plan_id' => max(0, (int) ($rule->suggested_plan_id ?? 0)),
        ];
    }

    /**
     * Handle mkt_offer_cta: / mkt_offer_apply: (WP Marketing_Automation::handle_callback_cta).
     *
     * @param  array{platform: string, chat_id: int, user: SvpUser}  $ctx
     */
    public function handleCallbackCta(array $ctx, int $offerId): void
    {
        $user = $ctx['user'] ?? null;
        $chatId = (int) ($ctx['chat_id'] ?? 0);
        $platform = (string) ($ctx['platform'] ?? 'telegram');
        if (! $user instanceof SvpUser || $offerId < 1 || $chatId < 1) {
            return;
        }

        $offer = DB::table('svp_marketing_offers')->where('id', $offerId)->first();
        if (! $offer || (int) ($offer->svp_user_id ?? 0) !== (int) $user->id) {
            app(\App\Modules\Core\Bot\Services\BotRuntime::class)->sendMessage(
                new \App\Modules\Core\Bot\BotContext($platform),
                $chatId,
                'Offer not found'
            );

            return;
        }
        $codeRow = DB::table('svp_discount_codes')->where('id', (int) ($offer->discount_code_id ?? 0))->first();
        $rule = (int) ($offer->rule_id ?? 0) > 0
            ? DB::table('svp_marketing_rules')->where('id', (int) $offer->rule_id)->first()
            : null;
        if (! $rule) {
            $rule = (object) ['id' => 0, 'segment_key' => 'never_purchased', 'message_body' => ''];
        }
        $mctx = $this->marketingContextForUser((int) $user->id, $rule);
        $cta = $this->resolveOfferCta($rule, $user, $mctx);
        $action = (string) ($cta['action'] ?? 'smart_checkout');
        $botCtx = new BotContext($platform);
        $runtime = app(\App\Modules\Core\Bot\Services\BotRuntime::class);
        $buy = app(\App\Modules\Core\Bot\Handlers\BuyHandler::class);
        $state = app(BotStateService::class);
        $checkout = app(\App\Modules\Core\Bot\Services\BotCommerceCheckoutService::class);
        $serviceCheckout = app(BotServiceCheckoutService::class);
        $checkoutUi = app(BotCheckoutUiService::class);

        $applyPending = function () use ($user, $codeRow, $offer, $checkout, $buy, $botCtx, $chatId, $runtime): bool {
            if (! $codeRow) {
                return false;
            }
            $tx = DB::table('svp_transactions')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->where('type', 'purchase')
                ->orderByDesc('id')
                ->first();
            if (! $tx) {
                return false;
            }
            $res = $checkout->applyDiscountCode((int) $tx->id, (string) $codeRow->code, (int) $user->id);
            if (empty($res['ok'])) {
                $runtime->sendMessage($botCtx, $chatId, 'Could not apply offer code');

                return false;
            }
            $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];
            $meta['marketing_offer_id'] = (int) $offer->id;
            DB::table('svp_transactions')->where('id', (int) $tx->id)->update([
                'meta_json' => json_encode($meta),
            ]);
            $runtime->sendMessage($botCtx, $chatId, 'Offer applied');
            $buy->refreshCheckoutAfterDiscount($botCtx, $user, $chatId, (int) $tx->id);

            return true;
        };

        $startBuy = function () use ($user, $codeRow, $offer, $cta, $state, $buy, $botCtx, $chatId): void {
            $planId = max(0, (int) ($cta['plan_id'] ?? 0));
            $state->set($user, 'marketing_offer_cta', [
                'offer_id' => (int) $offer->id,
                'code_id' => (int) ($codeRow->id ?? 0),
                'plan_id' => $planId,
            ]);
            if ($planId > 0) {
                $buy->handleCallback($botCtx, $user, [
                    'parts' => ['buy', 'cf', (string) $planId],
                    'chat_id' => $chatId,
                ]);

                return;
            }
            $buy->showBuyEntry($botCtx, $user, $chatId);
        };

        if ($action === 'smart_checkout') {
            if ($applyPending()) {
                return;
            }
            $startBuy();

            return;
        }
        if ($action === 'apply_pending') {
            if (! $applyPending()) {
                $runtime->sendMessage($botCtx, $chatId, 'No pending purchase to apply offer');
            }

            return;
        }
        if ($action === 'start_buy') {
            $startBuy();

            return;
        }
        if ($action === 'renew_same') {
            $this->renewWithOffer($botCtx, $user, $chatId, $offer, $codeRow, $cta, $runtime, $serviceCheckout, $checkout, $checkoutUi, $state, $startBuy);

            return;
        }
        if ($action === 'add_volume') {
            $this->addVolumeWithOffer($botCtx, $user, $chatId, $offer, $codeRow, $cta, $runtime, $state, $startBuy);

            return;
        }
    }

    /**
     * @param  array{action: string, button_key: string, plan_id: int, service_id: int}  $cta
     */
    protected function renewWithOffer(
        BotContext $botCtx,
        SvpUser $user,
        int $chatId,
        object $offer,
        ?object $codeRow,
        array $cta,
        \App\Modules\Core\Bot\Services\BotRuntime $runtime,
        BotServiceCheckoutService $serviceCheckout,
        \App\Modules\Core\Bot\Services\BotCommerceCheckoutService $checkout,
        BotCheckoutUiService $checkoutUi,
        BotStateService $state,
        callable $startBuy,
    ): void {
        $svcId = max(0, (int) ($cta['service_id'] ?? 0));
        $svc = $svcId > 0 ? SvpService::query()->find($svcId) : null;
        if (! $svc || (int) $svc->user_id !== (int) $user->id) {
            $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.marketing.no_active_service', $user, 'No active service'));
            $startBuy();

            return;
        }
        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.svc.renew_xray_only', $user, 'Renew is Xray only'));

            return;
        }
        $result = $serviceCheckout->beginRenew($user, $botCtx, (int) $svc->id);
        if (! empty($result['free'])) {
            $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.service.op_ok', $user, 'Done'));

            return;
        }
        if (empty($result['ok']) || empty($result['transaction_id'])) {
            $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.common.failed', $user, 'Failed'));

            return;
        }
        $txId = (int) $result['transaction_id'];
        if ($codeRow) {
            $res = $checkout->applyDiscountCode($txId, (string) $codeRow->code, (int) $user->id);
            if (! empty($res['ok'])) {
                $meta = json_decode((string) (DB::table('svp_transactions')->where('id', $txId)->value('meta_json') ?? '{}'), true);
                $meta = is_array($meta) ? $meta : [];
                $meta['marketing_offer_id'] = (int) $offer->id;
                DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta)]);
            }
        }
        $state->set($user, 'service_checkout', ['transaction_id' => $txId, 'service_id' => (int) $svc->id]);
        $amount = $checkout->transactionAmount($txId);
        $checkoutUi->sendCheckoutSummary($botCtx, $user, $chatId, 'Renew #'.(int) $svc->id, $amount, $txId, 'svc');
    }

    /**
     * @param  array{action: string, button_key: string, plan_id: int, service_id: int}  $cta
     */
    protected function addVolumeWithOffer(
        BotContext $botCtx,
        SvpUser $user,
        int $chatId,
        object $offer,
        ?object $codeRow,
        array $cta,
        \App\Modules\Core\Bot\Services\BotRuntime $runtime,
        BotStateService $state,
        callable $startBuy,
    ): void {
        $svcId = max(0, (int) ($cta['service_id'] ?? 0));
        $svc = $svcId > 0 ? SvpService::query()->find($svcId) : null;
        if (! $svc || (int) $svc->user_id !== (int) $user->id) {
            $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.marketing.no_active_service', $user, 'No active service'));
            $startBuy();

            return;
        }
        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.svc.volume_xray_only', $user, 'Volume add is Xray only'));

            return;
        }
        $state->set($user, 'service_addvol', [
            'service_id' => (int) $svc->id,
            'marketing_offer_id' => (int) $offer->id,
            'marketing_code_id' => (int) ($codeRow->id ?? 0),
        ]);
        $runtime->sendMessage($botCtx, $chatId, $this->texts->getForUser('msg.svc.prompt_add_volume_gb', $user, 'Send GB amount'));
    }
}
