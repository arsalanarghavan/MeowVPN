<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Jobs\DeferredConfigDeliveryJob;
use App\Modules\Core\Services\Portal\PortalConfigUriCollector;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Services\QrService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BotConfigDeliveryService
{
    /** @var list<int> backoff seconds after attempt 0 (WP config_delivery_retry_delays) */
    public const RETRY_DELAYS = [5, 10, 20, 30, 60, 120];

    /** WP SimpleVPBot_Handler_Service::CONFIG_SENT_TTL_SEC — dedupe window after successful delivery. */
    public const CONFIG_SENT_TTL_SEC = 900;

    public function __construct(
        protected PortalConfigUriCollector $collector,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotManagedUserContext $managed,
        protected QrService $qr,
        protected PortalLinkService $portalLinks,
        protected UiLayoutService $uiLayout,
    ) {}

    public function configAlreadySent(int $svpUserId, int $serviceId): bool
    {
        if ($svpUserId < 1 || $serviceId < 1) {
            return false;
        }

        return (bool) Cache::get($this->configSentCacheKey($svpUserId, $serviceId));
    }

    public function markConfigSent(int $svpUserId, int $serviceId): void
    {
        if ($svpUserId < 1 || $serviceId < 1) {
            return;
        }
        Cache::put($this->configSentCacheKey($svpUserId, $serviceId), '1', self::CONFIG_SENT_TTL_SEC);
    }

    /** Queue intro HTML/plain for the next auto config delivery (WP set_config_delivery_intro). */
    public function setConfigDeliveryIntro(int $svpUserId, int $serviceId, string $intro): void
    {
        if ($svpUserId < 1 || $serviceId < 1 || trim($intro) === '') {
            return;
        }
        Cache::put($this->configIntroCacheKey($svpUserId, $serviceId), $intro, 600);
    }

    public function peekConfigDeliveryIntro(int $svpUserId, int $serviceId): string
    {
        if ($svpUserId < 1 || $serviceId < 1) {
            return '';
        }
        $intro = Cache::get($this->configIntroCacheKey($svpUserId, $serviceId));

        return is_string($intro) ? trim($intro) : '';
    }

    public function clearConfigDeliveryIntro(int $svpUserId, int $serviceId): void
    {
        if ($svpUserId < 1 || $serviceId < 1) {
            return;
        }
        Cache::forget($this->configIntroCacheKey($svpUserId, $serviceId));
    }

    /**
     * WP build_purchase_delivery_intro_html — payment confirmed + ready summary.
     */
    public function buildPurchaseDeliveryIntro(SvpUser $user, ?object $svc = null): string
    {
        $lines = [$this->texts->getForUser('msg.purchase.payment_confirmed', $user, '✅ پرداخت شما تایید شد.')];
        if ($svc) {
            $label = trim((string) ($svc->display_label ?? $svc->remark ?? $svc->email ?? ''));
            if ($label !== '') {
                $lines[] = $this->texts->format(
                    $this->texts->getForUser('msg.service.ready_summary', $user, 'Service: {label}'),
                    ['label' => $label]
                );
            }
        }

        return implode("\n", $lines);
    }

    protected function configSentCacheKey(int $svpUserId, int $serviceId): string
    {
        return 'svp_cfg_sent_'.$svpUserId.'_'.$serviceId;
    }

    protected function configIntroCacheKey(int $svpUserId, int $serviceId): string
    {
        return 'svp_cfg_intro_'.$svpUserId.'_'.$serviceId;
    }

    /**
     * After purchase/approve: enqueue Telegram config + Bale subscription (WP ensure_subscription_delivered).
     * Skips re-dispatch when a delivery payload is already cached (receipt approve + fulfill both call this).
     */
    public function enqueueAfterProvision(SvpUser $user, int $serviceId): void
    {
        $serviceId = max(0, $serviceId);
        if ($serviceId < 1) {
            return;
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc || (string) ($svc->service_type ?? '') === 'l2tp') {
            return;
        }

        $ownerId = (int) $svc->user_id;
        if ($this->managed->isManagingOtherUser($user, $ownerId)) {
            $ownerId = (int) $user->id;
        }

        // WP set_config_delivery_intro before deferred send.
        $this->setConfigDeliveryIntro($ownerId, $serviceId, $this->buildPurchaseDeliveryIntro($user, $svc));

        $tried = false;
        $tgChat = (int) ($user->tg_user_id ?? 0);
        if ($tgChat > 0) {
            $tried = true;
            $key = $this->cacheKey($ownerId, $serviceId, 'telegram', 'config');
            if (! Cache::has($key)) {
                $this->enqueue(new BotContext('telegram'), $user, $tgChat, $serviceId, 'config');
            }
        }

        $baleChat = (int) ($user->bale_user_id ?? 0);
        if ($baleChat > 0) {
            $tried = true;
            $key = $this->cacheKey($ownerId, $serviceId, 'bale', 'subscription');
            if (! Cache::has($key)) {
                $this->enqueue(new BotContext('bale'), $user, $baleChat, $serviceId, 'subscription');
            }
        }

        if (! $tried) {
            $this->subscriptionDeliveryFallback($user, $serviceId);
        }
    }

    /**
     * WP subscription_delivery_fallback: portal URL + service panel button when config/sub cannot be sent.
     */
    public function subscriptionDeliveryFallback(SvpUser $user, int $serviceId, string $platform = ''): void
    {
        $serviceId = max(0, $serviceId);
        if ($serviceId < 1) {
            return;
        }

        $text = $this->texts->getForUser('msg.service.ready', $user, '🎉 سرویس جدید شما آماده است.');
        $portal = $this->buildServicePortalUrl((int) $user->id, $serviceId);
        if ($portal !== '') {
            $text .= "\n\n🌐 ".$portal;
        }
        $extra = [
            'reply_markup' => [
                'inline_keyboard' => [[
                    [
                        'text' => $this->texts->getForUser('btn.service.show_panel', $user, '🖥 جزئیات سرویس'),
                        'callback_data' => 'svc:p:'.$serviceId,
                    ],
                ]],
            ],
        ];

        $plat = strtolower(trim($platform));
        $targets = [];
        if ($plat === 'telegram' || $plat === 'bale') {
            $targets[] = $plat;
        } else {
            if ((int) ($user->tg_user_id ?? 0) > 0) {
                $targets[] = 'telegram';
            }
            if ((int) ($user->bale_user_id ?? 0) > 0) {
                $targets[] = 'bale';
            }
        }

        foreach ($targets as $p) {
            $chatId = $p === 'telegram' ? (int) $user->tg_user_id : (int) $user->bale_user_id;
            if ($chatId > 0) {
                $this->runtime->sendMessage(new BotContext($p), $chatId, $text, $extra);
            }
        }
    }

    public function enqueue(BotContext $ctx, SvpUser $user, int $chatId, int $serviceId, string $mode = 'config', ?string $cbId = null): void
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        $ownerId = $svc ? (int) $svc->user_id : (int) $user->id;
        if ($this->managed->isManagingOtherUser($user, $ownerId)) {
            $ownerId = (int) $user->id;
        }
        $platform = (string) $ctx->platform;
        $key = $this->cacheKey($ownerId, $serviceId, $platform, $mode);
        Cache::put($key, [
            'platform' => $platform,
            'chat_id' => $chatId,
            'service_id' => $serviceId,
            'mode' => $mode,
            'user_id' => (int) $user->id,
            'owner_id' => $ownerId,
            'cb_id' => $cbId,
            'attempt' => 0,
            'cache_key' => $key,
        ], 3600);

        DeferredConfigDeliveryJob::dispatch($ownerId, $serviceId, $platform, $mode);
    }

    /**
     * @return 'delivered'|'retry'|'failed'|'skip'
     */
    public function deliver(int $userId, int $serviceId, string $cbId = '', ?string $platform = null, ?string $modeHint = null): string
    {
        $payload = $this->resolvePayload($userId, $serviceId, $platform, $modeHint);
        if ($payload === null) {
            return 'skip';
        }

        $cacheKey = (string) ($payload['cache_key'] ?? $this->cacheKey(
            $userId,
            $serviceId,
            (string) ($payload['platform'] ?? 'telegram'),
            (string) ($payload['mode'] ?? 'config')
        ));

        $user = SvpUser::query()->find((int) ($payload['user_id'] ?? $userId));
        if (! $user) {
            Cache::forget($cacheKey);

            return 'skip';
        }

        $ctx = new BotContext((string) ($payload['platform'] ?? 'telegram'));
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $mode = (string) ($payload['mode'] ?? 'config');
        $callbackId = $cbId !== '' ? $cbId : (string) ($payload['cb_id'] ?? '');
        $attempt = max(0, (int) ($payload['attempt'] ?? 0));

        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.config_empty', $user, 'No config yet'));
            $this->ackCallback($ctx, $callbackId);
            Cache::forget($cacheKey);

            return 'failed';
        }

        $owner = SvpUser::query()->find((int) $svc->user_id) ?? $user;
        $ownerId = (int) $owner->id;

        // WP maybe_telegram_send_config_unified: skip re-send within CONFIG_SENT_TTL.
        if ($mode === 'config' && $this->configAlreadySent($ownerId, $serviceId)) {
            Cache::forget($cacheKey);
            $this->ackCallback($ctx, $callbackId);

            return 'delivered';
        }

        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            if (! svp_modules()->isEnabled('l2tp')) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.l2tp_disabled', $user, 'L2TP module disabled'));
                $this->ackCallback($ctx, $callbackId);
                Cache::forget($cacheKey);

                return 'failed';
            }
            $server = DB::table('svp_l2tp_servers')->where('id', (int) ($svc->l2tp_server_id ?? 0))->first();
            $body = $this->texts->format(
                $this->texts->getForUser('msg.service.l2tp_config', $user, "L2TP\nHost: {host}\nUser: {user}\nPass: {pass}\nPSK: {psk}"),
                [
                    'host' => (string) ($server->l2tp_host ?? '—'),
                    'user' => (string) ($svc->l2tp_username ?? '—'),
                    'pass' => $this->resolveL2tpPassword($svc),
                    'psk' => $this->resolveL2tpPsk($server),
                ]
            );
            $this->runtime->sendMessage($ctx, $chatId, $body);
            Cache::forget($cacheKey);
            $this->ackCallback($ctx, $callbackId);

            return 'delivered';
        }

        $data = $this->collector->collect($owner, $serviceId);
        $uris = is_array($data['uris'] ?? null) ? $data['uris'] : [];
        if ($uris === []) {
            return $this->scheduleRetryOrExhaust($payload, $cacheKey, $ctx, $chatId, $user, $callbackId, $attempt, $serviceId);
        }

        $primary = (string) $uris[0];
        $portal = $this->buildServicePortalUrl($ownerId, $serviceId);

        if ($mode === 'link' || $mode === 'subscription') {
            if ($mode === 'subscription' && strlen($primary) > 4000) {
                $subBase = trim((string) (DB::table('svp_panels')->where('id', (int) ($svc->panel_id ?? 0))->value('subscription_public_base') ?? ''));
                if ($subBase !== '') {
                    $primary = rtrim($subBase, '/').'/sub/'.(string) ($svc->sub_id ?? $svc->email ?? $serviceId);
                }
            }
            if ($primary === '') {
                return $this->scheduleRetryOrExhaust($payload, $cacheKey, $ctx, $chatId, $user, $callbackId, $attempt, $serviceId);
            }
            $qrIntro = $this->texts->getForUser(
                'msg.purchase.delivery_qr_intro',
                $user,
                "📱 Scan the QR or open the subscription link.\n➖➖➖➖➖➖➖➖"
            );
            $markup = $this->uiLayout->buildPurchaseQrKeyboard($serviceId, $user, $portal);
            $this->maybeSendQr($ctx, $chatId, $primary, $qrIntro, $markup);
            if ($mode === 'link') {
                $this->runtime->sendMessage($ctx, $chatId, $primary, ['reply_markup' => $markup]);
            }
            Cache::forget($cacheKey);
            $this->ackCallback($ctx, $callbackId);

            return 'delivered';
        }

        // mode = config: WP telegram_send_config_unified — QR + configs intro with copy buttons.
        $intro = $this->peekConfigDeliveryIntro($ownerId, $serviceId);
        $qrCaption = $intro !== ''
            ? $intro
            : $this->texts->getForUser(
                'msg.purchase.delivery_qr_intro',
                $user,
                "📱 Scan the QR or open the subscription link.\n➖➖➖➖➖➖➖➖"
            );
        $qrMarkup = $this->uiLayout->buildPurchaseQrKeyboard($serviceId, $user, $portal);
        $this->maybeSendQr($ctx, $chatId, $primary, $qrCaption, $qrMarkup);

        $cfgIntro = $this->texts->getForUser(
            'msg.purchase.delivery_configs_intro',
            $user,
            "🧾 Single subscriptions\n➖➖➖➖➖➖➖➖\nUse the buttons below to copy each config or copy all at once."
        );
        $cfgRows = $this->uiLayout->buildPurchaseConfigsRows($serviceId, [
            'uris' => $uris,
            'config_uris' => $uris,
        ], $user);
        if ($cfgRows !== []) {
            $this->runtime->sendMessage($ctx, $chatId, $cfgIntro, [
                'reply_markup' => ['inline_keyboard' => $cfgRows],
            ]);
        } else {
            $body = $this->texts->getForUser('msg.service.config_header', $user, 'Your config:');
            foreach (array_slice($uris, 0, 5) as $u) {
                $body .= "\n".$u;
            }
            if (! empty($data['userinfo'])) {
                $body .= "\n\n".(string) $data['userinfo'];
            }
            $this->runtime->sendMessage($ctx, $chatId, $body);
        }

        $this->clearConfigDeliveryIntro($ownerId, $serviceId);
        $this->markConfigSent($ownerId, $serviceId);
        Cache::forget($cacheKey);
        $this->ackCallback($ctx, $callbackId);

        return 'delivered';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return 'retry'|'failed'
     */
    protected function scheduleRetryOrExhaust(
        array $payload,
        string $cacheKey,
        BotContext $ctx,
        int $chatId,
        SvpUser $user,
        string $callbackId,
        int $attempt,
        int $serviceId,
    ): string {
        $delays = self::RETRY_DELAYS;
        if ($attempt >= count($delays)) {
            Log::info('bot.config_delivery.exhausted', [
                'user_id' => (int) $user->id,
                'service_id' => $serviceId,
                'platform' => $payload['platform'] ?? null,
                'mode' => $payload['mode'] ?? null,
                'attempt' => $attempt,
            ]);
            $this->ackCallback($ctx, $callbackId);
            Cache::forget($cacheKey);
            $this->subscriptionDeliveryFallback(
                $user,
                $serviceId,
                (string) ($payload['platform'] ?? '')
            );

            return 'failed';
        }

        $delay = $delays[$attempt];
        $nextAttempt = $attempt + 1;
        $payload['attempt'] = $nextAttempt;
        Cache::put($cacheKey, $payload, 3600);

        $ownerId = (int) ($payload['owner_id'] ?? $payload['user_id'] ?? 0);
        $platform = (string) ($payload['platform'] ?? 'telegram');
        $mode = (string) ($payload['mode'] ?? 'config');

        // Sync driver ignores delay and would immediately re-enter until exhausted;
        // leave payload for DeferredConfigDeliveryCronJob (WP cron retry style).
        if (config('queue.default') !== 'sync') {
            DeferredConfigDeliveryJob::dispatch($ownerId, $serviceId, $platform, $mode)
                ->delay(now()->addSeconds($delay));
        }

        Log::debug('bot.config_delivery.retry_scheduled', [
            'service_id' => $serviceId,
            'attempt' => $nextAttempt,
            'delay' => $delay,
            'queue' => config('queue.default'),
        ]);

        return 'retry';
    }

    /** @return array<string, mixed>|null */
    protected function resolvePayload(int $userId, int $serviceId, ?string $platform, ?string $modeHint): ?array
    {
        if ($platform !== null && $modeHint !== null) {
            $key = $this->cacheKey($userId, $serviceId, $platform, $modeHint);
            $payload = Cache::get($key);
            if (is_array($payload)) {
                $payload['cache_key'] = $key;

                return $payload;
            }
        }

        // Legacy key without platform/mode (pre-retry shape).
        $legacy = Cache::get("bot_config_delivery:{$userId}:{$serviceId}");
        if (is_array($legacy)) {
            $legacy['cache_key'] = "bot_config_delivery:{$userId}:{$serviceId}";

            return $legacy;
        }

        foreach (['telegram', 'bale'] as $plat) {
            foreach (['config', 'subscription', 'link'] as $mode) {
                $key = $this->cacheKey($userId, $serviceId, $plat, $mode);
                $payload = Cache::get($key);
                if (is_array($payload)) {
                    $payload['cache_key'] = $key;

                    return $payload;
                }
            }
        }

        return null;
    }

    protected function ackCallback(BotContext $ctx, string $cbId): void
    {
        if ($cbId === '') {
            return;
        }
        $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId, 'ok' => true]);
    }

    protected function maybeSendQr(
        BotContext $ctx,
        int $chatId,
        string $uri,
        string $caption = 'QR',
        ?array $replyMarkup = null,
    ): void {
        if ($uri === '' || strlen($uri) > 2000) {
            return;
        }
        $path = $this->qr->tempPng($uri);
        if ($path !== null) {
            $params = [];
            if ($replyMarkup !== null) {
                $params['reply_markup'] = $replyMarkup;
            }
            // Prefer photo with caption; BotRuntime::sendLocalPhoto only takes caption string.
            $sent = $this->runtime->sendLocalPhoto($ctx, $chatId, $path, $caption !== '' ? $caption : 'QR');
            @unlink($path);
            if ($sent !== null) {
                if ($replyMarkup !== null) {
                    // Photo API may not attach markup via sendLocalPhoto — send markup as follow-up noop row via message if needed.
                    // Markup already on separate configs message for purchase flow.
                }

                return;
            }
        }
        Log::debug('bot.config_delivery.qr_fallback', ['chat_id' => $chatId]);
        $extra = $replyMarkup !== null ? ['reply_markup' => $replyMarkup] : [];
        $this->runtime->sendMessage($ctx, $chatId, ($caption !== '' && $caption !== 'QR' ? $caption."\n\n" : '').$uri, $extra);
    }

    protected function resolveL2tpPsk(?object $server): string
    {
        if (! $server) {
            return '—';
        }
        $enc = trim((string) ($server->l2tp_psk_enc ?? ''));
        if ($enc !== '') {
            try {
                return Crypt::decryptString($enc);
            } catch (\Throwable) {
                return '—';
            }
        }

        return (string) ($server->ipsec_psk ?? '—');
    }

    protected function resolveL2tpPassword(object $svc): string
    {
        $plain = trim((string) ($svc->l2tp_password ?? ''));
        if ($plain !== '') {
            return $plain;
        }
        $enc = trim((string) ($svc->l2tp_password_enc ?? ''));
        if ($enc === '') {
            return '—';
        }
        try {
            return Crypt::decryptString($enc);
        } catch (\Throwable) {
            return '—';
        }
    }

    public function cacheKey(int $userId, int $serviceId, string $platform = 'telegram', string $mode = 'config'): string
    {
        return "bot_config_delivery:{$userId}:{$serviceId}:{$platform}:{$mode}";
    }

    protected function buildServicePortalUrl(int $userId, int $serviceId): string
    {
        if ($userId < 1 || $serviceId < 1) {
            return '';
        }
        $link = $this->portalLinks->buildPortalLink($userId, PortalLinkService::CUSTOMER_TTL, $serviceId);

        return url('/info').'?'.http_build_query([
            'svp_p' => '1',
            'svp_u' => $link['svp_u'],
            'svp_sid' => $serviceId,
            'svp_e' => $link['svp_e'],
            'svp_s' => $link['svp_s'],
        ]);
    }
}
