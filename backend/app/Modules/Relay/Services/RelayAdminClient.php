<?php

namespace App\Modules\Relay\Services;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;

class RelayAdminClient
{
    public function __construct(protected SettingsStore $settings) {}

    public function isConfigured(): bool
    {
        return $this->adminUrl() !== '' && $this->sharedSecret() !== '';
    }

    public function adminUrl(): string
    {
        $url = trim((string) $this->settings->get('telegram_relay_admin_url', ''));
        if ($url === '') {
            $ip = trim((string) $this->settings->get('telegram_relay_vps_ip', ''));
            if ($ip !== '') {
                $url = 'https://'.preg_replace('#^https?://#i', '', $ip);
            }
        }
        if ($url === '') {
            $url = trim((string) $this->settings->get('telegram_relay_base_url', ''));
        }

        return rtrim($url, '/');
    }

    public function sharedSecret(): string
    {
        $sec = trim((string) $this->settings->get('telegram_relay_shared_secret', ''));
        if ($sec !== '') {
            return $sec;
        }

        return trim((string) $this->settings->get('telegram_relay_master_secret', ''));
    }

    public function sslVerify(): bool
    {
        return (bool) $this->settings->get('telegram_relay_admin_ssl_verify', false);
    }

    /** @return array{ok: bool, status?: int, data?: array<string, mixed>, message?: string} */
    public function get(string $path, int $timeout = 15): array
    {
        return $this->request('GET', $path, [], $timeout);
    }

    /** @param  array<string, mixed>  $body */
    public function post(string $path, array $body = [], int $timeout = 25): array
    {
        return $this->request('POST', $path, $body, $timeout);
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok: bool, status?: int, data?: array<string, mixed>, message?: string}
     */
    protected function request(string $method, string $path, array $body = [], int $timeout = 25): array
    {
        $base = $this->adminUrl();
        $secret = $this->sharedSecret();
        if ($base === '' || $secret === '') {
            return ['ok' => false, 'message' => 'relay_not_configured'];
        }

        $url = $base.'/'.ltrim($path, '/');
        $client = Http::withHeaders(['X-SVP-Relay-Secret' => $secret])
            ->withOptions(['verify' => $this->sslVerify()])
            ->timeout(max(5, $timeout));

        try {
            $response = strtoupper($method) === 'GET'
                ? $client->get($url)
                : $client->post($url, $body);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        $code = $response->status();
        $data = $response->json();
        if (! is_array($data)) {
            $data = [];
        }

        $ok = $code >= 200 && $code < 300 && ! empty($data['ok']);

        return [
            'ok' => $ok,
            'status' => $code,
            'data' => $data,
            'message' => isset($data['error']) ? (string) $data['error'] : ($ok ? '' : 'relay_http_'.$code),
        ];
    }
}
