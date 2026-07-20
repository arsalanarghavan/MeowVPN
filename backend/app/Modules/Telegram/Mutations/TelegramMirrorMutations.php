<?php

namespace App\Modules\Telegram\Mutations;

use App\Modules\Core\Bot\Clients\TelegramApiClient;
use App\Modules\Telegram\Services\TelegramMirrorBotService;
use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Cache;

class TelegramMirrorMutations
{
    public function __construct(
        protected TelegramMirrorBotService $mirrors,
        protected SettingsStore $settings,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'telegram_mirror_save' => [self::class, 'save'],
            'telegram_mirror_delete' => [self::class, 'delete'],
            'telegram_mirror_set_webhook' => [self::class, 'setWebhook'],
            'telegram_mirror_delete_webhook' => [self::class, 'deleteWebhook'],
            'telegram_mirror_toggle' => [self::class, 'toggle'],
            'telegram_mirror_test' => [self::class, 'test'],
            'telegram_mirror_diagnostics' => [self::class, 'diagnostics'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function save(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        $fields = [];
        if (array_key_exists('label', $payload)) {
            $fields['label'] = (string) $payload['label'];
        }
        if (array_key_exists('telegram_token', $payload) && trim((string) $payload['telegram_token']) !== '') {
            $fields['telegram_token'] = (string) $payload['telegram_token'];
        }
        if (array_key_exists('telegram_secret_token', $payload)) {
            $fields['telegram_secret_token'] = (string) $payload['telegram_secret_token'];
        }
        if (array_key_exists('enabled', $payload)) {
            $fields['enabled'] = ! empty($payload['enabled']);
        }

        if ($mid < 1) {
            $newId = $this->mirrors->insert($fields);
            if (! $newId) {
                return svp_err('insert_failed');
            }
            if (! empty($fields['telegram_token'])) {
                $this->mirrors->syncBotUsername((int) $newId);
            }

            return svp_ok(['mirror_id' => (int) $newId]);
        }

        if (! $this->mirrors->find($mid)) {
            return svp_err('not_found');
        }

        $this->mirrors->update($mid, $fields);
        if (! empty($fields['telegram_token'])) {
            $this->mirrors->syncBotUsername($mid);
        }

        return svp_ok(['mirror_id' => $mid]);
    }

    /** @param  array<string, mixed>  $payload */
    public function delete(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        if ($mid < 1) {
            return svp_err('bad_id');
        }

        $this->deleteWebhook(['mirror_id' => $mid], $actor);
        $this->mirrors->delete($mid);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function setWebhook(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        if ($mid < 1) {
            return svp_err('bad_id');
        }

        $bot = $this->mirrors->find($mid);
        $token = $this->mirrors->tokenFor($bot);
        if ($token === '') {
            return svp_err('token_missing');
        }

        $secret = $this->mirrors->webhookSecretPlaintext($bot);
        if ($secret === '') {
            $secret = $this->mirrors->ensureWebhookSecret($mid);
        }
        if ($secret === '') {
            return svp_err('webhook_secret_missing');
        }

        $url = $this->mirrors->expectedWebhookUrl($mid);
        $params = [
            'url' => $url,
            'allowed_updates' => ['message', 'callback_query'],
            'drop_pending_updates' => true,
        ];
        $hdr = $bot ? trim((string) $bot->telegram_secret_token) : '';
        if ($hdr !== '') {
            $params['secret_token'] = $hdr;
        }

        $res = (new TelegramApiClient($token))->setWebhook($params);
        if (! is_array($res) || empty($res['ok'])) {
            return svp_err('set_webhook_failed', ['response' => $res]);
        }

        $this->mirrors->syncBotUsername($mid);

        return svp_ok(['url' => $url, 'response' => $res]);
    }

    /** @param  array<string, mixed>  $payload */
    public function deleteWebhook(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        if ($mid < 1) {
            return svp_err('bad_id');
        }

        $bot = $this->mirrors->find($mid);
        $token = $this->mirrors->tokenFor($bot);
        if ($token === '') {
            return svp_err('token_missing');
        }

        $res = (new TelegramApiClient($token))->deleteWebhook(['drop_pending_updates' => true]);
        if (! is_array($res) || empty($res['ok'])) {
            return svp_err('delete_webhook_failed', ['response' => $res]);
        }

        return svp_ok(['response' => $res]);
    }

    /** @param  array<string, mixed>  $payload */
    public function toggle(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        if ($mid < 1 || ! $this->mirrors->find($mid)) {
            return svp_err('bad_id');
        }

        $this->mirrors->update($mid, ['enabled' => ! empty($payload['enabled'])]);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function test(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        $token = trim((string) ($payload['telegram_token'] ?? ''));
        if ($mid > 0 && $token === '') {
            $token = $this->mirrors->tokenFor($this->mirrors->find($mid));
        }
        if ($token === '') {
            return svp_err('no_token');
        }

        $me = (new TelegramApiClient($token))->getMe();
        if (! is_array($me) || empty($me['ok'])) {
            return svp_err('get_me_failed', ['data' => $me]);
        }

        $username = (string) ($me['result']['username'] ?? '');
        if ($mid > 0 && $username !== '') {
            $this->mirrors->saveBotUsername($mid, $username);
        }

        return svp_ok($me['result'] ?? $me);
    }

    /** @param  array<string, mixed>  $payload */
    public function diagnostics(array $payload, ?Authenticatable $actor): array
    {
        $mid = (int) ($payload['mirror_id'] ?? 0);
        if ($mid < 1) {
            return svp_err('bad_id');
        }

        $cacheKey = 'svp_diag_rl_mirror_'.$mid.'_'.(int) ($actor?->getAuthIdentifier() ?? 0);
        if (Cache::has($cacheKey)) {
            return svp_err('rate_limited');
        }
        Cache::put($cacheKey, 1, 30);

        $bot = $this->mirrors->find($mid);
        $token = $this->mirrors->tokenFor($bot);
        $reveal = ! empty($payload['reveal_token']);
        $issues = [];

        if ($token === '') {
            $issues[] = [
                'code' => 'token_missing',
                'severity' => 'error',
                'message' => 'Mirror bot token is not configured.',
            ];
        }

        $expectedUrl = $this->mirrors->expectedWebhookUrl($mid);
        $local = [
            'scope' => 'mirror',
            'mirror_bot_id' => $mid,
            'platform' => 'telegram',
            'plugin_bot_processing_enabled' => (bool) $this->settings->get('bot_enabled', true),
            'platform_enabled' => (bool) $this->settings->get('telegram_enabled', true),
            'mirror_enabled' => $bot ? (bool) $bot->enabled : null,
            'expected_webhook_url' => $expectedUrl,
            'webhook_secret_configured' => $expectedUrl !== '',
            'telegram_secret_token_set' => $bot ? trim((string) $bot->telegram_secret_token) !== '' : false,
            'can_reveal_token' => true,
        ];

        if ($bot && ! $bot->enabled) {
            $issues[] = [
                'code' => 'mirror_disabled',
                'severity' => 'warning',
                'message' => 'Mirror bot is disabled.',
            ];
        }

        $getMe = null;
        $webhookInfo = null;
        if ($token !== '') {
            $client = new TelegramApiClient($token);
            $getMe = $client->getMe();
            $webhookInfo = $client->apiCall('getWebhookInfo', []);
            if ($reveal) {
                $local['telegram_token'] = $token;
            }
        }

        if (! empty($payload['send_outbound_ping']) && $token !== '') {
            $adminIds = (array) $this->settings->get('admin_telegram_ids', []);
            $chatId = (int) ($adminIds[0] ?? 0);
            if ($chatId > 0) {
                $local['outbound_ping'] = (new TelegramApiClient($token))->sendMessage([
                    'chat_id' => $chatId,
                    'text' => 'MeowVPN mirror bot diagnostics ping',
                ]);
            }
        }

        return svp_ok([
            'local' => $local,
            'getMe' => $getMe,
            'webhookInfo' => $webhookInfo,
            'issues' => $issues,
            'can_reveal_token' => true,
        ]);
    }
}
