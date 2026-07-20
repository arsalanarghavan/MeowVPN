<?php

namespace App\Modules\XuiPanel\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class XuiHttpTransport
{
    private int $requestTimeoutSec = 90;

    /** @var array<string, bool> */
    protected array $readyState = [];

    /** @var array{login_attempts:int, session_reuse:int, probes:int} */
    protected array $sessionStats = [
        'login_attempts' => 0,
        'session_reuse' => 0,
        'probes' => 0,
    ];

    public function __construct(
        protected XuiPanelContext $ctx,
        protected XuiSessionStore $sessions,
    ) {}

    public function clearSession(): void
    {
        $this->sessions->clear($this->ctx->panelId);
        $this->ctx->resolvedAuthBase = '';
        $this->ctx->lastAuthFlow = '';
        $this->readyState = [];
    }

    /** @return array{login_attempts:int, session_reuse:int, probes:int, panel_id:int} */
    public function getSessionStats(): array
    {
        return array_merge($this->sessionStats, ['panel_id' => $this->ctx->panelId]);
    }

    protected function bumpSessionStat(string $stat): void
    {
        if (isset($this->sessionStats[$stat])) {
            $this->sessionStats[$stat]++;
        }
    }

    protected function readyCacheKey(bool $cookieOnly): string
    {
        return ($cookieOnly ? 'cookie:' : 'api:').(string) $this->ctx->panelId;
    }

    protected function probeHttpOk(int $code): bool
    {
        return $code >= 200 && $code < 300;
    }

    public function probePanelSession(bool $cookieOnly = false): bool
    {
        if ($this->ctx->panelRoot() === '') {
            return false;
        }
        $this->bumpSessionStat('probes');
        $url = $this->resolveUrl('server/status', 'api');
        $headers = ['Accept' => 'application/json'];
        $creds = $this->ctx->credentials();
        $token = $cookieOnly ? '' : trim((string) ($creds['panel_api_token'] ?? ''));
        if ($token !== '') {
            $headers['Authorization'] = 'Bearer '.$token;
        } else {
            $cookie = $this->sessions->getCookie($this->ctx->panelId);
            if ($cookie === '') {
                return false;
            }
            $headers['Cookie'] = $cookie;
            $csrf = $this->sessions->getCsrf($this->ctx->panelId);
            if ($csrf !== '') {
                $headers['X-CSRF-Token'] = $csrf;
            }
        }
        $code = Http::timeout(12)->withHeaders($headers)->get($url)->status();

        return $this->probeHttpOk($code);
    }

    public function ensureReady(bool $forceReauth = false, bool $cookieOnly = false): bool
    {
        $cacheKey = $this->readyCacheKey($cookieOnly);
        if (! $forceReauth && isset($this->readyState[$cacheKey])) {
            return $this->readyState[$cacheKey];
        }
        if ($this->ctx->panelRoot() === '') {
            $this->readyState[$cacheKey] = false;

            return false;
        }
        if (! $cookieOnly && $this->ctx->hasApiToken()) {
            if (! $forceReauth && $this->probePanelSession(false)) {
                $this->bumpSessionStat('session_reuse');
                $this->ctx->lastAuthFlow = 'bearer';
                $this->readyState[$cacheKey] = true;

                return true;
            }
            $this->readyState[$cacheKey] = false;

            return false;
        }
        if (! $this->ctx->hasCookieCredentials()) {
            $this->readyState[$cacheKey] = false;

            return false;
        }
        if (! $forceReauth && $this->sessions->getCookie($this->ctx->panelId) !== ''
            && $this->probePanelSession(true)) {
            $this->bumpSessionStat('session_reuse');
            $this->readyState[$cacheKey] = true;

            return true;
        }
        if ($this->loginWithCookieSession(6, 350000)) {
            $this->bumpSessionStat('login_attempts');
            $this->readyState[$cacheKey] = true;

            return true;
        }
        $this->readyState[$cacheKey] = false;

        return false;
    }

    public function diagUrl(string $path, string $scope = 'api'): string
    {
        return $this->resolveUrl($path, $scope);
    }

    /** @param  array{ok?:bool, json?:array|null}  $r */
    public function parseConfigJsonResponse(array $r): string|false
    {
        if (empty($r['ok']) || ! is_array($r['json'] ?? null)) {
            return false;
        }
        $json = $r['json'];
        if (! isset($json['obj'])) {
            return false;
        }
        $obj = $json['obj'];
        if (is_string($obj)) {
            return $obj;
        }
        if (is_array($obj)) {
            $encoded = json_encode($obj, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return is_string($encoded) ? $encoded : false;
        }

        return false;
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

    public function setRequestTimeout(int $seconds): int
    {
        $prev = $this->requestTimeoutSec;
        $this->requestTimeoutSec = max(5, $seconds);

        return $prev;
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
        bool $binary = false,
    ): array {
        $path = ltrim($path, '/');
        $url = $this->resolveUrl($path, 'api');
        $headers = ['Accept' => $binary ? 'application/octet-stream,*/*' : 'application/json'];
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
            ? ($body === [] ? $pending->post($url) : $pending->post($url, $body))
            : $pending->get($url);

        $code = $response->status();
        $raw = (string) $response->body();
        $json = $binary ? null : json_decode($raw, true);
        $json = is_array($json) ? $json : null;

        if (in_array($code, [401, 403], true) && $retry > 0 && $token === '') {
            $this->clearSession();
            if ($this->loginWithCookieSession(4, 300000)) {
                return $this->request($path, $method, $body, $sessionOnly, $retry - 1, $binary);
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

    /**
     * Multipart POST server/importDB (SQLite upload).
     *
     * @return array{ok:bool, message?:string, code?:int, json?:array|null}
     */
    public function requestImportDb(string $dbPath, string $filename = 'x-ui.db', int $retry = 2): array
    {
        if ($dbPath === '' || ! is_readable($dbPath)) {
            return ['ok' => false, 'message' => 'unreadable_db'];
        }
        $data = (string) file_get_contents($dbPath);
        if ($data === '') {
            return ['ok' => false, 'message' => 'read_failed'];
        }
        $fn = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename) ?: 'x-ui.db';
        $boundary = '----'.bin2hex(random_bytes(8));
        $body = "--{$boundary}\r\n";
        $body .= "Content-Disposition: form-data; name=\"db\"; filename=\"{$fn}\"\r\n";
        $body .= "Content-Type: application/octet-stream\r\n\r\n";
        $body .= $data."\r\n";
        $body .= "--{$boundary}--\r\n";

        $url = $this->resolveUrl('server/importDB', 'api');
        $headers = [
            'Content-Type' => 'multipart/form-data; boundary='.$boundary,
            'Accept' => 'application/json',
        ];
        $creds = $this->ctx->credentials();
        $token = trim((string) ($creds['panel_api_token'] ?? ''));
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

        $res = Http::timeout(max(60, $this->requestTimeoutSec))->withHeaders($headers)->withBody($body, 'multipart/form-data; boundary='.$boundary)->post($url);
        $code = $res->status();
        $json = json_decode((string) $res->body(), true);
        if (in_array($code, [401, 403], true) && $retry > 0 && $token === '') {
            $this->clearSession();
            if ($this->loginWithCookieSession(4, 300000)) {
                return $this->requestImportDb($dbPath, $filename, $retry - 1);
            }
        }
        if ($code >= 200 && $code < 300 && $this->responseIsSuccess(is_array($json) ? $json : null)) {
            return ['ok' => true, 'code' => $code, 'json' => is_array($json) ? $json : null];
        }

        return [
            'ok' => false,
            'code' => $code,
            'message' => is_array($json) ? $this->panelJsonMsg($json) : trim(substr((string) $res->body(), 0, 200)),
            'json' => is_array($json) ? $json : null,
        ];
    }

    public function panelJsonMsg(mixed $json): string
    {
        return is_array($json) ? trim((string) ($json['msg'] ?? '')) : '';
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

    public function resolveUrl(string $path, string $scope = 'api'): string
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
