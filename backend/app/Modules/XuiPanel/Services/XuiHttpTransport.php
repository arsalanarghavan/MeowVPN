<?php

namespace App\Modules\XuiPanel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XuiHttpTransport
{
    private int $requestTimeoutSec = 90;

    public function __construct(
        protected XuiPanelContext $ctx,
        protected XuiSessionStore $sessions,
    ) {}

    public function clearSession(): void
    {
        $this->sessions->clear($this->ctx->panelId);
        $this->ctx->resolvedAuthBase = '';
        $this->ctx->lastAuthFlow = '';
    }

    public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool
    {
        if ($this->ctx->hasApiToken()) {
            return $this->ctx->panelRoot() !== '';
        }

        return $this->loginWithCookieSession($maxAttempts, $delayUs);
    }

    public function loginWithCookieSession(int $maxAttempts = 6, int $delayUs = 350000): bool
    {
        $c = $this->ctx->credentials();
        if (trim((string) ($c['panel_username'] ?? '')) === ''
            || trim((string) ($c['panel_password'] ?? '')) === ''
            || $this->ctx->panelRoot() === '') {
            return false;
        }

        $max = max(1, min(12, $maxAttempts));
        for ($i = 0; $i < $max; $i++) {
            if ($i > 0) {
                $this->clearSession();
                usleep(max(50000, $delayUs + ($i - 1) * 100000));
            }
            if ($this->loginViaCookieSession()) {
                return true;
            }
        }

        return false;
    }

    public function login(): bool
    {
        if ($this->ctx->hasApiToken() && ! $this->ctx->hasCookieCredentials()) {
            $this->ctx->lastAuthFlow = 'bearer';

            return $this->ctx->panelRoot() !== '';
        }

        return $this->loginViaCookieSession();
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok:bool, code:int, body:string, json:array|null, url:string}
     */
    public function request(
        string $path,
        string $method = 'GET',
        array $body = [],
        bool $sessionOnly = false,
        int $retry = 2,
    ): array {
        $path = ltrim($path, '/');
        $url = $this->resolveUrl($path, 'api');
        $headers = ['Accept' => 'application/json'];
        $creds = $this->ctx->credentials();
        $token = $sessionOnly ? '' : trim((string) ($creds['panel_api_token'] ?? ''));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        } else {
            $cookie = $this->sessions->getCookie($this->ctx->panelId);
            if ($cookie !== '') {
                $headers['Cookie'] = $cookie;
            }
            $csrf = $this->sessions->getCsrf($this->ctx->panelId);
            if ($csrf !== '') {
                $headers['X-CSRF-Token'] = $csrf;
            }
        }

        $pending = Http::timeout($this->requestTimeoutSec)->withHeaders($headers);
        $response = strtoupper($method) === 'POST'
            ? $pending->post($url, $body)
            : $pending->get($url);

        $code = $response->status();
        $raw = (string) $response->body();
        $json = json_decode($raw, true);
        $json = is_array($json) ? $json : null;

        if (in_array($code, [401, 403], true) && $retry > 0 && $token === '') {
            $this->clearSession();
            if ($this->loginWithCookieSession(4, 300000)) {
                return $this->request($path, $method, $body, $sessionOnly, $retry - 1);
            }
        }

        return [
            'ok' => $code >= 200 && $code < 300,
            'code' => $code,
            'body' => $raw,
            'json' => $json,
            'url' => $url,
        ];
    }

    public function apiHttpOk(array $r): bool
    {
        if (empty($r['ok'])) {
            return false;
        }
        $j = $r['json'] ?? null;
        if (is_array($j) && array_key_exists('success', $j)) {
            return ! empty($j['success']);
        }

        return true;
    }

    public function responseIsSuccess(mixed $res): bool
    {
        if (! is_array($res)) {
            return false;
        }
        if (array_key_exists('success', $res) && $res['success'] === true) {
            return true;
        }
        if (! empty($res['success'])) {
            return true;
        }
        if (! empty($res['obj'])) {
            return true;
        }

        return false;
    }

    public function detectApiFlavor(): string
    {
        $rV3 = $this->request('clients/list/paged?page=1&pageSize=1', 'GET');
        if ($this->apiHttpOk($rV3)) {
            $flavor = XuiPanelContext::FLAVOR_V3;
        } else {
            $code = (int) ($rV3['code'] ?? 0);
            if ($code === 404) {
                $flavor = XuiPanelContext::FLAVOR_LEGACY;
            } else {
                $rInb = $this->request('inbounds/list', 'GET');
                $flavor = $this->apiHttpOk($rInb) ? XuiPanelContext::FLAVOR_LEGACY : XuiPanelContext::FLAVOR_UNKNOWN;
            }
        }
        $this->ctx->setApiFlavor($flavor);

        return $flavor;
    }

    public function getApiFlavor(bool $refresh = false): string
    {
        $flavor = $this->ctx->getApiFlavor($refresh);
        if ($flavor === XuiPanelContext::FLAVOR_UNKNOWN || $refresh) {
            if ($this->login()) {
                $flavor = $this->detectApiFlavor();
            }
        }

        return $flavor;
    }

    protected function loginViaCookieSession(): bool
    {
        $c = $this->ctx->credentials();
        $csrf = false;
        if (! $this->sessions->hasNoCsrf($this->ctx->panelId)) {
            $csrf = $this->ensureCsrfToken();
        }
        if (is_array($csrf) && $this->loginModernCookie($csrf, $c)) {
            $this->ctx->lastAuthFlow = 'modern_cookie';

            return true;
        }

        $this->sessions->setCsrf($this->ctx->panelId, '');
        if ($this->loginLegacyCookie($c)) {
            $this->ctx->lastAuthFlow = 'legacy_cookie';

            return true;
        }

        return false;
    }

    /** @return array{token:string,cookie:string}|false */
    protected function ensureCsrfToken(): array|false
    {
        $token = $this->sessions->getCsrf($this->ctx->panelId);
        $cookie = $this->sessions->getCookie($this->ctx->panelId);
        if ($token !== '' && $cookie !== '') {
            return ['token' => $token, 'cookie' => $cookie];
        }

        $bases = $this->authBaseCandidates();
        foreach ($bases as $base) {
            $base = rtrim((string) $base, '/');
            if ($base === '') {
                continue;
            }
            $cookieTry = $cookie;
            $url = $base.'/csrf-token';
            $headers = $this->browserHeaders();
            if ($cookieTry !== '') {
                $headers['Cookie'] = $cookieTry;
            }
            $res = Http::timeout(30)->withHeaders($headers)->get($url);
            if ($res->status() === 404) {
                $this->sessions->markNoCsrf($this->ctx->panelId);

                return false;
            }
            $json = $res->json();
            if ($res->status() !== 200 || ! is_array($json) || empty($json['success']) || empty($json['obj'])) {
                continue;
            }
            $newCookie = $this->cookieFromResponse($res->headers());
            if ($newCookie !== '') {
                $cookieTry = $this->mergeCookies($cookieTry, $newCookie);
            }
            if ($cookieTry === '') {
                continue;
            }
            $token = (string) $json['obj'];
            $this->ctx->resolvedAuthBase = $base;
            $this->sessions->setAuthBase($this->ctx->panelId, $base);
            $this->sessions->setCookie($this->ctx->panelId, $cookieTry);
            $this->sessions->setCsrf($this->ctx->panelId, $token);

            return ['token' => $token, 'cookie' => $cookieTry];
        }

        return false;
    }

    /** @param  array{token:string,cookie:string}  $csrf */
    /** @param  array<string, mixed>  $c */
    protected function loginModernCookie(array $csrf, array $c): bool
    {
        $base = $this->ctx->resolvedAuthBase !== ''
            ? $this->ctx->resolvedAuthBase
            : rtrim($this->ctx->panelRoot(), '/');
        $url = $base.'/login';
        $body = [
            'username' => (string) ($c['panel_username'] ?? ''),
            'password' => (string) ($c['panel_password'] ?? ''),
            'twoFactorCode' => (string) ($c['panel_login_secret'] ?? ''),
        ];

        return $this->attemptLoginPost($url, $body, [
            'Cookie' => (string) $csrf['cookie'],
            'X-CSRF-Token' => (string) $csrf['token'],
            'X-Requested-With' => 'XMLHttpRequest',
        ], (string) $csrf['cookie'], true);
    }

    /** @param  array<string, mixed>  $c */
    protected function loginLegacyCookie(array $c): bool
    {
        foreach ($this->authBaseCandidates() as $base) {
            $base = rtrim((string) $base, '/');
            if ($base === '') {
                continue;
            }
            $this->ctx->resolvedAuthBase = $base;
            $url = $base.'/login';
            $body = [
                'username' => (string) ($c['panel_username'] ?? ''),
                'password' => (string) ($c['panel_password'] ?? ''),
                'loginSecret' => (string) ($c['panel_login_secret'] ?? ''),
            ];
            if ((string) ($c['panel_login_secret'] ?? '') !== '') {
                $body['twoFactorCode'] = (string) $c['panel_login_secret'];
            }
            if ($this->attemptLoginPost($url, $body, [], '', false)) {
                $this->sessions->setAuthBase($this->ctx->panelId, $base);

                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $extraHeaders
     */
    protected function attemptLoginPost(
        string $url,
        array $body,
        array $extraHeaders = [],
        string $fallbackCookie = '',
        bool $storeCsrf = false,
    ): bool {
        $headers = array_merge($this->browserHeaders(), $extraHeaders);
        $res = Http::timeout(50)->withHeaders($headers)->asJson()->post($url, $body);
        if (! $res->successful()) {
            $res = Http::timeout(50)->withHeaders($headers)->asForm()->post($url, $body);
        }
        $json = $res->json();
        $ok = is_array($json) && (! empty($json['success']) || ! empty($json['obj']));
        if (! $ok && ! $res->successful()) {
            return false;
        }
        $cookie = $this->cookieFromResponse($res->headers());
        if ($cookie === '' && $fallbackCookie !== '') {
            $cookie = $fallbackCookie;
        }
        if ($cookie === '') {
            return false;
        }
        $this->sessions->setCookie($this->ctx->panelId, $cookie);
        if ($storeCsrf && isset($extraHeaders['X-CSRF-Token'])) {
            $this->sessions->setCsrf($this->ctx->panelId, (string) $extraHeaders['X-CSRF-Token']);
        }

        return true;
    }

    /** @return array<int, string> */
    protected function authBaseCandidates(): array
    {
        $root = rtrim($this->ctx->panelRoot(), '/');
        if ($root === '') {
            return [];
        }
        $out = [];
        $seen = [];
        $add = function (string $base) use (&$out, &$seen) {
            $base = rtrim($base, '/');
            if ($base === '' || isset($seen[$base])) {
                return;
            }
            $seen[$base] = true;
            $out[] = $base;
        };
        $cached = $this->sessions->getAuthBase($this->ctx->panelId);
        if ($cached !== '') {
            $add($cached);
        }
        $add($root);

        return $out;
    }

    protected function resolveUrl(string $path, string $scope = 'api'): string
    {
        if ($scope === 'api') {
            return rtrim($this->ctx->apiRoot(), '/').'/'.ltrim($path, '/');
        }

        return rtrim($this->ctx->panelRoot(), '/').'/'.ltrim($path, '/');
    }

    /** @return array<string, string> */
    protected function browserHeaders(): array
    {
        return [
            'Accept' => 'application/json, text/html, */*',
            'User-Agent' => 'SimpleVPBot-Laravel/1.0',
            'Accept-Language' => 'en-US,en;q=0.9',
        ];
    }

    /** @param  array<string, array<int, string>|string>  $headers */
    protected function cookieFromResponse(array $headers): string
    {
        $setCookie = $headers['Set-Cookie'] ?? $headers['set-cookie'] ?? [];
        if (is_string($setCookie)) {
            $setCookie = [$setCookie];
        }
        $parts = [];
        foreach ($setCookie as $line) {
            if (preg_match('/^([^=;]+)=([^;]+)/', (string) $line, $m)) {
                $parts[] = trim($m[1]).'='.trim($m[2]);
            }
        }

        return implode('; ', array_unique($parts));
    }

    protected function mergeCookies(string $existing, string $fromRes): string
    {
        $jar = [];
        foreach (array_filter(array_map('trim', explode(';', $existing))) as $part) {
            if (preg_match('/^([^=]+)=(.*)$/', $part, $m)) {
                $jar[trim($m[1])] = trim($m[2]);
            }
        }
        foreach (array_filter(array_map('trim', explode(';', $fromRes))) as $part) {
            if (preg_match('/^([^=]+)=(.*)$/', $part, $m)) {
                $jar[trim($m[1])] = trim($m[2]);
            }
        }
        if ($jar === []) {
            return $existing;
        }

        return implode('; ', array_map(fn ($k, $v) => $k.'='.$v, array_keys($jar), $jar));
    }
}
