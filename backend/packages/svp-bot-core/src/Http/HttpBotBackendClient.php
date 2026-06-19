<?php

namespace Svp\Bot\Core\Http;

use Svp\Bot\Core\Contracts\BotBackendClient;

class HttpBotBackendClient implements BotBackendClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $secret,
        protected string $platform,
        protected int $timeoutSec = 25,
    ) {}

    public function processUpdate(string $platform, array $update, int $resellerSvpUserId = 0, bool $async = true): array
    {
        $body = array_merge($update, [
            'reseller_svp_user_id' => $resellerSvpUserId,
            'async' => $async,
        ]);

        return $this->request('POST', 'process-update', $body, $platform);
    }

    public function resolveUser(string $platform, array $from, int $resellerSvpUserId = 0, bool $create = false, string $startText = ''): array
    {
        return $this->request('POST', 'user/resolve', [
            'from' => $from,
            'create' => $create,
            'start_text' => $startText,
            'reseller_svp_user_id' => $resellerSvpUserId,
        ], $platform);
    }

    public function texts(array $keys, string $locale = 'fa'): array
    {
        $res = $this->request('GET', 'texts', ['keys' => $keys, 'locale' => $locale]);
        $texts = $res['texts'] ?? [];

        return is_array($texts) ? $texts : [];
    }

    public function settings(array $keys): array
    {
        $res = $this->request('GET', 'settings', ['keys' => $keys]);
        $settings = $res['settings'] ?? [];

        return is_array($settings) ? $settings : [];
    }

    public function mutate(int $actorSvpUserId, string $op, array $params = []): array
    {
        return $this->request('POST', 'mutate', [
            'actor_svp_user_id' => $actorSvpUserId,
            'op' => $op,
            'params' => $params,
        ]);
    }

    public function health(): array
    {
        return $this->request('GET', 'health');
    }

    /** @param  array<string, mixed>  $body */
    protected function request(string $method, string $path, array $body = [], ?string $platform = null): array
    {
        $url = rtrim($this->baseUrl, '/').'/api/v1/internal/bot/'.$path;
        if ($method === 'GET' && $body !== []) {
            $url .= '?'.http_build_query($body);
        }

        $headers = [
            'X-SVP-Bot-Service-Secret: '.$this->secret,
            'X-SVP-Platform: '.($platform ?? $this->platform),
            'Accept: application/json',
            'Content-Type: application/json',
        ];

        $opts = [
            'http' => [
                'method' => $method,
                'header' => implode("\r\n", $headers),
                'timeout' => $this->timeoutSec,
                'ignore_errors' => true,
            ],
        ];

        if ($method === 'POST') {
            $opts['http']['content'] = json_encode($body, JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $ctx = stream_context_create($opts);
        $raw = @file_get_contents($url, false, $ctx);
        if ($raw === false) {
            return ['ok' => false, 'message' => 'backend_unreachable'];
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : ['ok' => false, 'message' => 'invalid_json'];
    }
}
