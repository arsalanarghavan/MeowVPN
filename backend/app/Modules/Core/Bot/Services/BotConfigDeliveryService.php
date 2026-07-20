<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Services\Portal\PortalConfigUriCollector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use App\Services\QrService;
use Illuminate\Support\Facades\Log;

class BotConfigDeliveryService
{
    public function __construct(
        protected PortalConfigUriCollector $collector,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotManagedUserContext $managed,
        protected QrService $qr,
    ) {}

    public function enqueue(BotContext $ctx, SvpUser $user, int $chatId, int $serviceId, string $mode = 'config', ?string $cbId = null): void
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        $ownerId = $svc ? (int) $svc->user_id : (int) $user->id;
        if ($this->managed->isManagingOtherUser($user, $ownerId)) {
            $ownerId = (int) $user->id;
        }
        Cache::put($this->cacheKey($ownerId, $serviceId), [
            'platform' => $ctx->platform,
            'chat_id' => $chatId,
            'service_id' => $serviceId,
            'mode' => $mode,
            'user_id' => (int) $user->id,
            'owner_id' => $ownerId,
            'cb_id' => $cbId,
        ], 600);

        \App\Modules\Core\Bot\Jobs\DeferredConfigDeliveryJob::dispatch($ownerId, $serviceId);
    }

    public function deliver(int $userId, int $serviceId, string $cbId = ''): void
    {
        $payload = Cache::get($this->cacheKey($userId, $serviceId));
        if (! is_array($payload)) {
            return;
        }

        $user = SvpUser::query()->find((int) ($payload['user_id'] ?? $userId));
        if (! $user) {
            return;
        }

        $ctx = new BotContext((string) ($payload['platform'] ?? 'telegram'));
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $mode = (string) ($payload['mode'] ?? 'config');
        $callbackId = $cbId !== '' ? $cbId : (string) ($payload['cb_id'] ?? '');

        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.config_empty', $user, 'No config yet'));
            $this->ackCallback($ctx, $callbackId);

            return;
        }

        $owner = SvpUser::query()->find((int) $svc->user_id) ?? $user;

        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            if (! svp_modules()->isEnabled('l2tp')) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.l2tp_disabled', $user, 'L2TP module disabled'));
                $this->ackCallback($ctx, $callbackId);

                return;
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
            Cache::forget($this->cacheKey($userId, $serviceId));
            $this->ackCallback($ctx, $callbackId);

            return;
        }

        $data = $this->collector->collect($owner, $serviceId);
        $uris = is_array($data['uris'] ?? null) ? $data['uris'] : [];
        if ($uris === []) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.config_empty', $user, 'No config yet'));
            $this->ackCallback($ctx, $callbackId);

            return;
        }

        $primary = (string) $uris[0];
        if ($mode === 'link' || $mode === 'subscription') {
            if ($mode === 'subscription' && strlen($primary) > 4000) {
                $subBase = trim((string) (DB::table('svp_panels')->where('id', (int) ($svc->panel_id ?? 0))->value('subscription_public_base') ?? ''));
                if ($subBase !== '') {
                    $primary = rtrim($subBase, '/').'/sub/'.(string) ($svc->sub_id ?? $svc->email ?? $serviceId);
                }
            }
            $this->runtime->sendMessage($ctx, $chatId, $primary);
            $this->maybeSendQr($ctx, $chatId, $primary);
            Cache::forget($this->cacheKey($userId, $serviceId));
            $this->ackCallback($ctx, $callbackId);

            return;
        }

        $body = $this->texts->getForUser('msg.service.config_header', $user, 'Your config:');
        foreach (array_slice($uris, 0, 5) as $u) {
            $body .= "\n".$u;
        }
        if (! empty($data['userinfo'])) {
            $body .= "\n\n".(string) $data['userinfo'];
        }
        $this->runtime->sendMessage($ctx, $chatId, $body);
        $this->maybeSendQr($ctx, $chatId, $primary);
        Cache::forget($this->cacheKey($userId, $serviceId));
        $this->ackCallback($ctx, $callbackId);
    }

    protected function ackCallback(BotContext $ctx, string $cbId): void
    {
        if ($cbId === '') {
            return;
        }
        $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId, 'ok' => true]);
    }

    protected function maybeSendQr(BotContext $ctx, int $chatId, string $uri): void
    {
        if ($uri === '' || strlen($uri) > 2000) {
            return;
        }
        $path = $this->qr->tempPng($uri);
        if ($path !== null) {
            $sent = $this->runtime->sendLocalPhoto($ctx, $chatId, $path, 'QR');
            @unlink($path);
            if ($sent !== null) {
                return;
            }
        }
        Log::debug('bot.config_delivery.qr_fallback', ['chat_id' => $chatId]);
        $this->runtime->sendMessage($ctx, $chatId, $uri);
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

    protected function cacheKey(int $userId, int $serviceId): string
    {
        return "bot_config_delivery:{$userId}:{$serviceId}";
    }
}
