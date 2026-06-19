<?php

namespace App\Modules\Reseller\Services;

use App\Modules\Core\Bot\Clients\BaleApiClient;
use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Relay\Services\TelegramRelayService;
use Illuminate\Support\Facades\DB;

class ResellerWebhookService
{
    public function __construct(
        protected ResellerBotProfileService $profiles,
        protected BotRuntime $runtime,
        protected TelegramRelayService $relay,
    ) {}

    /** @return array{ok: bool, message?: string, url?: string, result?: mixed} */
    public function setWebhook(int $resellerId, string $platform): array
    {
        $platform = $platform === 'bale' ? 'bale' : 'telegram';

        if ($platform === 'telegram' && $this->relay->isEnabled()) {
            return $this->relay->setWebhookViaRelay('reseller', $resellerId, true);
        }

        $profile = $this->profiles->findByReseller($resellerId);
        if (! $profile) {
            return svp_err('profile_missing');
        }

        $token = $this->profiles->tokenForPlatform($profile, $platform);
        if ($token === '') {
            return svp_err('token_missing');
        }

        $secret = $this->profiles->webhookSecretPlaintext($profile);
        if ($secret === '') {
            $secret = $this->profiles->ensureWebhookSecret($resellerId);
        }

        $url = $this->runtime->webhookUrl($platform, $secret, $resellerId);
        $client = $platform === 'bale' ? new BaleApiClient($token) : new TelegramApiClient($token);

        $params = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query'],
            'drop_pending_updates' => true,
        ];

        $hdr = trim((string) ($profile->telegram_secret_token ?? ''));
        if ($platform === 'telegram' && $hdr !== '') {
            $params['secret_token'] = $hdr;
        }

        $result = $client->setWebhook($params);
        if (empty($result['ok'])) {
            return svp_err('webhook_failed', ['url' => $url, 'result' => $result]);
        }

        $me = $client->getMe();
        if (! empty($me['ok']) && ! empty($me['result']['username'])) {
            $this->profiles->saveBotUsername($resellerId, $platform, (string) $me['result']['username']);
        }

        return svp_ok(['url' => $url, 'result' => $result]);
    }

    /** @return array{ok: bool, message?: string, result?: mixed} */
    public function deleteWebhook(int $resellerId, string $platform): array
    {
        $platform = $platform === 'bale' ? 'bale' : 'telegram';
        $profile = $this->profiles->findByReseller($resellerId);
        if (! $profile) {
            return svp_ok();
        }

        $token = $this->profiles->tokenForPlatform($profile, $platform);
        if ($token === '') {
            return svp_ok();
        }

        $client = $platform === 'bale' ? new BaleApiClient($token) : new TelegramApiClient($token);
        $result = $client->deleteWebhook(['drop_pending_updates' => true]);

        return svp_ok(['result' => $result]);
    }

    public function resellerIsApproved(int $resellerId): bool
    {
        $user = DB::table('svp_users')->where('id', $resellerId)->first();

        return $user
            && (string) $user->role === 'reseller'
            && (string) $user->status === 'approved';
    }
}
