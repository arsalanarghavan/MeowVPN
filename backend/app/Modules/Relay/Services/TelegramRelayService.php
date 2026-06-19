<?php

namespace App\Modules\Relay\Services;

use App\Modules\Reseller\Services\ResellerBotProfileService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class TelegramRelayService
{
    public function __construct(
        protected SettingsStore $settings,
        protected RelayAdminClient $client,
    ) {}

    protected function botProfiles(): ?ResellerBotProfileService
    {
        if (! svp_modules()->isEnabled('reseller')) {
            return null;
        }

        return app(ResellerBotProfileService::class);
    }

    public function isEnabled(): bool
    {
        if ((bool) $this->settings->get('telegram_relay_force', false)) {
            return $this->client->adminUrl() !== '';
        }
        if (! (bool) $this->settings->get('telegram_relay_enabled', false)) {
            return false;
        }

        return $this->client->adminUrl() !== '';
    }

    public function tenantId(): string
    {
        return (string) $this->settings->get('telegram_relay_tenant_id', '');
    }

    public function publicUrl(): string
    {
        $url = trim((string) $this->settings->get('telegram_relay_public_url', ''));

        return $url !== '' ? rtrim($url, '/') : '';
    }

    public function publicUrlForReseller(int $resellerId): string
    {
        if ($resellerId > 0 && $this->botProfiles()) {
            $profile = $this->botProfiles()->findByReseller($resellerId);
            if ($profile) {
                $custom = trim((string) ($profile->telegram_relay_public_url ?? ''));
                if ($custom !== '') {
                    return rtrim($custom, '/');
                }
            }
        }

        return $this->publicUrl();
    }

    public function forwardBaseUrl(): string
    {
        $url = trim((string) $this->settings->get('telegram_relay_laravel_forward_url', ''));
        if ($url === '') {
            $url = (string) $this->settings->get('public_site_url', config('app.url'));
        }

        return rtrim($url, '/');
    }

    public function botApiBaseUrl(string $token): string
    {
        $base = rtrim($this->client->adminUrl(), '/').'/';
        $tok = rawurlencode($token);

        return $base.'bot'.$tok.'/';
    }

    /** @return array<int, string> */
    public function collectDomains(): array
    {
        $hosts = [];
        $add = function (string $url) use (&$hosts): void {
            $u = trim($url);
            if ($u === '') {
                return;
            }
            if (! preg_match('#^https?://#i', $u)) {
                $u = 'https://'.$u;
            }
            $host = parse_url($u, PHP_URL_HOST);
            if (is_string($host) && $host !== '') {
                $hosts[] = strtolower($host);
            }
        };

        $add($this->publicUrl());

        if (Schema::hasTable('svp_reseller_bot_profiles')) {
            $rows = DB::table('svp_reseller_bot_profiles')
                ->where('telegram_relay_public_url', '<>', '')
                ->pluck('telegram_relay_public_url');
            foreach ($rows as $row) {
                $add((string) $row);
            }
        }

        return array_values(array_unique(array_filter($hosts)));
    }

    /** @return array<string, mixed> */
    public function buildConfigSnapshot(): array
    {
        $mainEnabled = (bool) $this->settings->get('telegram_enabled', true);
        $resellers = [];

        $profiles = $this->botProfiles();
        if ($profiles && Schema::hasTable('svp_reseller_bot_profiles')) {
            $rows = DB::table('svp_reseller_bot_profiles')->get();
            foreach ($rows as $row) {
                $rid = (int) $row->reseller_svp_user_id;
                if ($rid < 1) {
                    continue;
                }
                $entry = [
                    'reseller_svp_user_id' => $rid,
                    'telegram_token' => $profiles->tokenForPlatform($row, 'telegram'),
                    'webhook_secret' => $profiles->webhookSecretPlaintext($row),
                    'telegram_secret_token' => trim((string) ($row->telegram_secret_token ?? '')),
                    'enabled' => ! isset($row->enabled) || (int) $row->enabled !== 0,
                    'telegram_enabled' => ! isset($row->telegram_enabled) || (int) $row->telegram_enabled !== 0,
                    'admin_telegram_ids' => $this->decodeAdminIds((string) ($row->admin_telegram_ids ?? '')),
                ];
                $relayPub = trim((string) ($row->telegram_relay_public_url ?? ''));
                if ($relayPub !== '') {
                    $entry['relay_public_url'] = rtrim($relayPub, '/');
                }
                $resellers[] = $entry;
            }
        }

        $forward = $this->forwardBaseUrl();

        return [
            'tenant_id' => $this->tenantId(),
            'domains' => $this->collectDomains(),
            'config_version' => (string) time(),
            'laravel_base_url' => $forward,
            'relay_public_url' => $this->publicUrl(),
            'main' => [
                'telegram_token' => (string) $this->settings->get('telegram_bot_token', $this->settings->get('telegram_token', '')),
                'telegram_webhook_secret' => (string) $this->settings->get('telegram_webhook_secret', ''),
                'telegram_secret_header' => (string) $this->settings->get('telegram_secret_header', ''),
                'telegram_enabled' => $mainEnabled,
                'enabled' => (bool) $this->settings->get('bot_enabled', true),
                'admin_telegram_ids' => array_values(array_map('intval', (array) $this->settings->get('admin_telegram_ids', []))),
            ],
            'resellers' => $resellers,
        ];
    }

    /** @return array{ok: bool, data?: array<string, mixed>, message?: string} */
    public function health(): array
    {
        $result = $this->client->get('/internal/health', 15);
        \Illuminate\Support\Facades\Log::channel('svp-relay')->info('relay.health', [
            'ok' => ! empty($result['ok']),
            'message' => (string) ($result['message'] ?? ''),
        ]);

        return $result;
    }

    /** @return array{ok: bool, data?: array<string, mixed>, message?: string} */
    public function status(): array
    {
        return $this->client->get('/internal/status', 20);
    }

    /** @return array{ok: bool, data?: array<string, mixed>, message?: string} */
    public function pushConfigToRelay(): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'relay_disabled'];
        }

        $res = $this->client->post('/internal/config', $this->buildConfigSnapshot(), 30);
        if (empty($res['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'sync_failed'),
            ];
        }

        $this->settings->set('telegram_relay_last_sync_at', time());
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        if (! empty($data['tenant_id'])) {
            $tid = (string) $data['tenant_id'];
            if ($this->tenantId() !== $tid) {
                $this->settings->set('telegram_relay_tenant_id', $tid);
            }
        }

        return ['ok' => true, 'data' => $data];
    }

    /** @return array{ok: bool, data?: array<string, mixed>, message?: string} */
    public function domainsSync(): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'relay_disabled'];
        }

        return $this->client->post('/internal/domains/sync', ['domains' => $this->collectDomains()], 25);
    }

    /** @return array{ok: bool, data?: array<string, mixed>, message?: string} */
    public function setWebhookViaRelay(string $scope = 'main', int $resellerId = 0, bool $dropPending = true): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'relay_disabled'];
        }

        $sync = $this->pushConfigToRelay();
        if (empty($sync['ok'])) {
            return $sync;
        }

        $res = $this->client->post('/internal/set-webhook', [
            'scope' => $scope === 'reseller' ? 'reseller' : 'main',
            'reseller_svp_user_id' => $resellerId,
            'drop_pending_updates' => $dropPending,
        ], 35);

        if (empty($res['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($res['message'] ?? 'set_webhook_failed'),
                'data' => is_array($res['data'] ?? null) ? $res['data'] : [],
            ];
        }

        return [
            'ok' => true,
            'data' => is_array($res['data'] ?? null) ? $res['data'] : [],
        ];
    }

    /** @return array{ok: bool, data?: array<string, mixed>, message?: string} */
    public function deleteWebhookViaRelay(string $scope = 'main', int $resellerId = 0): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'relay_disabled'];
        }

        return $this->client->post('/internal/delete-webhook', [
            'scope' => $scope === 'reseller' ? 'reseller' : 'main',
            'reseller_svp_user_id' => $resellerId,
        ], 30);
    }

    public function ensureRelaySecret(): string
    {
        $sec = $this->client->sharedSecret();
        if ($sec !== '') {
            return $sec;
        }

        return $this->rotateRelaySecret();
    }

    public function rotateRelaySecret(): string
    {
        $secret = Str::random(48);
        $this->settings->set('telegram_relay_shared_secret', $secret);

        return $secret;
    }

    /** @return array{ok: bool, steps?: array<int, array<string, mixed>>, message?: string} */
    public function autoSyncAfterSave(): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'relay_disabled'];
        }

        $steps = [];
        $health = $this->health();
        $steps[] = ['step' => 'health', 'ok' => ! empty($health['ok'])];
        if (empty($health['ok'])) {
            return ['ok' => false, 'message' => 'relay_unreachable', 'steps' => $steps];
        }

        $sync = $this->pushConfigToRelay();
        $steps[] = ['step' => 'config', 'ok' => ! empty($sync['ok'])];
        if (empty($sync['ok'])) {
            return ['ok' => false, 'message' => 'config_sync_failed', 'steps' => $steps];
        }

        $dom = $this->domainsSync();
        $steps[] = ['step' => 'domains', 'ok' => ! empty($dom['ok'])];

        $laravelIp = $this->detectOutboundIp();
        if ($laravelIp !== '') {
            $render = $this->client->post('/internal/admin/nginx/render', ['ips' => [$laravelIp]], 30);
            $steps[] = ['step' => 'nginx_render', 'ok' => ! empty($render['ok'])];
        }

        return [
            'ok' => ! empty($dom['ok']),
            'steps' => $steps,
        ];
    }

    public function maybeSyncAfterSettings(string $tab): void
    {
        if ($tab !== 'relay' || ! $this->isEnabled()) {
            return;
        }

        $this->autoSyncAfterSave();
    }

    /** @return array{ok: bool, message?: string, data?: array<string, mixed>} */
    public function adminProxy(string $method, string $path, array $payload = [], int $timeout = 45): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'relay_disabled'];
        }

        if (strtoupper($method) === 'GET' && isset($payload['lines'])) {
            $path .= (str_contains($path, '?') ? '&' : '?').'lines='.(int) $payload['lines'];
        }

        $res = strtoupper($method) === 'GET'
            ? $this->client->get($path, $timeout)
            : $this->client->post($path, $payload, $timeout);

        return [
            'ok' => ! empty($res['ok']),
            'message' => (string) ($res['message'] ?? ''),
            'data' => is_array($res['data'] ?? null) ? $res['data'] : [],
        ];
    }

    public function expectedWebhookUrlMain(string $platform = 'telegram'): string
    {
        if ($platform !== 'telegram' || ! $this->isEnabled()) {
            return '';
        }
        $sec = (string) $this->settings->get('telegram_webhook_secret', '');
        if ($sec === '') {
            return '';
        }

        return $this->publicUrl().'/webhook/telegram/'.rawurlencode($sec);
    }

    public function expectedWebhookUrlReseller(string $platform, int $resellerId): string
    {
        if ($platform !== 'telegram' || $resellerId < 1 || ! $this->isEnabled()) {
            return '';
        }

        $profiles = $this->botProfiles();
        if (! $profiles) {
            return '';
        }

        $profile = $profiles->findByReseller($resellerId);
        if (! $profile) {
            return '';
        }

        $sec = $profiles->webhookSecretPlaintext($profile);
        if ($sec === '') {
            return '';
        }

        return $this->publicUrlForReseller($resellerId).'/webhook/telegram/reseller/'.$resellerId.'/'.rawurlencode($sec);
    }

    protected function detectOutboundIp(): string
    {
        return (string) Cache::remember('svp_outbound_ip', 86400, function () {
            try {
                $ip = trim((string) Http::timeout(8)->get('https://api.ipify.org?format=text')->body());
                if ($ip !== '' && filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            } catch (\Throwable) {
                //
            }

            return '';
        });
    }

    /** @return array<int, int> */
    protected function decodeAdminIds(string $raw): array
    {
        if ($raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('intval', $decoded), fn ($v) => $v > 0));
        }

        $parts = preg_split('/[\s,;]+/', $raw) ?: [];

        return array_values(array_filter(array_map('intval', $parts), fn ($v) => $v > 0));
    }
}
