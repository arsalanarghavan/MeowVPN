<?php

namespace App\Modules\PasarGuard\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * PasarGuard panel API client (v5.x REST) — port of SimpleVPBot_Pasarguard_Client.
 */
class PasarGuardClient
{
    public const PROVIDER = 'pasarguard';

    public const FLAVOR = 'pasarguard_v5';

    protected PasarGuardPanelContext $ctx;

    protected PasarGuardSessionStore $sessions;

    protected int $requestTimeoutSec = 90;

    public function __construct(
        ?PasarGuardPanelContext $ctx = null,
        ?PasarGuardSessionStore $sessions = null,
    ) {
        $this->sessions = $sessions ?? new PasarGuardSessionStore;
        $this->ctx = $ctx ?? new PasarGuardPanelContext;
    }

    /** @param  array<string, mixed>  $panel */
    public function runWithPanel(int $panelId, callable $fn, array $panel = []): mixed
    {
        $prevCtx = $this->ctx;
        $this->ctx = new PasarGuardPanelContext;
        $this->ctx->bind($panelId, $panel);
        try {
            return $fn($this);
        } finally {
            $this->ctx = $prevCtx;
        }
    }

    public function getProvider(): string
    {
        return self::PROVIDER;
    }

    public function isPasarguardApi(): bool
    {
        return true;
    }

    public function isV3ClientsApi(): bool
    {
        return true;
    }

    public function clearSession(): void
    {
        $this->sessions->clear($this->ctx->panelId);
    }

    public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool
    {
        for ($i = 0; $i < max(1, $maxAttempts); $i++) {
            if ($this->login()) {
                return true;
            }
            if ($i + 1 < $maxAttempts && $delayUs > 0) {
                usleep($delayUs);
            }
        }

        return false;
    }

    public function ensureReady(bool $forceReauth = false): bool
    {
        if ($forceReauth) {
            $this->clearSession();
        }
        if ($this->bearerToken() !== '' && ! $forceReauth) {
            return true;
        }

        return $this->login();
    }

    /** @return array{login_attempts:int, session_reuse:int, probes:int, panel_id:int} */
    public function getSessionStats(): array
    {
        return [
            'login_attempts' => 0,
            'session_reuse' => $this->bearerToken() !== '' ? 1 : 0,
            'probes' => 0,
            'panel_id' => $this->ctx->panelId,
        ];
    }

    public function login(): bool
    {
        if ($this->bearerToken() !== '' && $this->sessions->getToken($this->ctx->panelId) !== '') {
            $this->ctx->lastAuthFlow = 'bearer_cached';
            $this->ctx->lastAuthDiag = [
                'auth_flow' => 'bearer_cached',
                'login_http_code' => 200,
            ];

            return true;
        }

        $c = $this->ctx->credentials();
        $user = trim((string) ($c['panel_username'] ?? ''));
        $pass = (string) ($c['panel_password'] ?? '');
        if ($user === '' || $pass === '') {
            // Static API token without username/password is still usable.
            if (trim((string) ($c['panel_api_token'] ?? '')) !== '') {
                $this->ctx->lastAuthFlow = 'bearer';
                $this->ctx->lastAuthDiag = [
                    'auth_flow' => 'bearer',
                    'login_http_code' => 200,
                ];

                return $this->ctx->panelRoot() !== '';
            }
            $this->ctx->lastAuthDiag = [
                'auth_flow' => 'missing_credentials',
                'login_http_code' => 0,
            ];

            return false;
        }

        $url = $this->ctx->resolveUrl('admin/token');
        $res = Http::timeout(30)
            ->withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/x-www-form-urlencoded',
            ])
            ->asForm()
            ->post($url, [
                'username' => $user,
                'password' => $pass,
                'grant_type' => 'password',
            ]);

        $code = $res->status();
        $json = $res->json();
        $this->ctx->lastAuthFlow = 'jwt_login';
        $this->ctx->lastAuthDiag = [
            'auth_flow' => 'jwt_login',
            'login_url' => $url,
            'login_http_code' => $code,
        ];

        if (! is_array($json) || empty($json['access_token'])) {
            $failMsg = is_array($json) ? $this->panelJsonMsg($json) : '';
            if ($failMsg === '' && $res->body() !== '') {
                $failMsg = substr(preg_replace('/\s+/', ' ', (string) $res->body()) ?? '', 0, 240);
            }
            if ($failMsg !== '') {
                $this->ctx->lastAuthDiag['login_body_msg'] = $failMsg;
            }

            return false;
        }

        $token = (string) $json['access_token'];
        $this->sessions->setToken($this->ctx->panelId, $token);
        $this->ctx->persistApiToken($token);
        $this->ctx->setApiFlavor(self::FLAVOR);

        return true;
    }

    protected function bearerToken(): string
    {
        $stored = $this->sessions->getToken($this->ctx->panelId);
        if ($stored !== '') {
            return $stored;
        }

        return trim((string) ($this->ctx->credentials()['panel_api_token'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array{ok:bool, code:int, body:string, json:array|null, url:string}
     */
    public function request(
        string $path,
        string $method = 'GET',
        array $body = [],
        bool $binary = false,
        int $retry = 2,
    ): array {
        if ($this->bearerToken() === '') {
            $this->ensureReady(false);
        }

        $url = $this->ctx->resolveUrl($path);
        $method = strtoupper($method);
        $maxAttempt = 4;
        $last = null;

        for ($attempt = 0; $attempt < $maxAttempt; $attempt++) {
            $headers = [
                'Accept' => $binary ? 'application/octet-stream,*/*' : 'application/json',
            ];
            $token = $this->bearerToken();
            if ($token !== '') {
                $headers['Authorization'] = 'Bearer '.$token;
            }

            $pending = Http::timeout(max(5, $this->requestTimeoutSec))->withHeaders($headers);
            $response = match ($method) {
                'POST' => $body === [] ? $pending->post($url) : $pending->asJson()->post($url, $body),
                'PUT' => $body === [] ? $pending->put($url) : $pending->asJson()->put($url, $body),
                'PATCH' => $body === [] ? $pending->patch($url) : $pending->asJson()->patch($url, $body),
                'DELETE' => $body === [] ? $pending->delete($url) : $pending->asJson()->delete($url, $body),
                default => $pending->get($url),
            };

            $code = $response->status();
            $last = $response;
            if ($this->responseIsTransientFailure($response->successful() ? null : true, $code) && $attempt + 1 < $maxAttempt) {
                // Network / 5xx — retry. Laravel Http throws on connection errors via failed(),
                // so treat non-2xx high codes and 0 as transient below via status.
                if ($code <= 0 || ($code >= 500 && $code < 600) || $code === 408 || $code === 429) {
                    usleep(200000 + $attempt * 120000);
                    continue;
                }
            }
            break;
        }

        $res = $last;
        $code = $res ? $res->status() : 0;
        $raw = $res ? (string) $res->body() : '';

        if (in_array($code, [401, 403], true) && $retry > 0) {
            $this->clearSession();
            if ($this->login()) {
                return $this->request($path, $method, $body, $binary, $retry - 1);
            }
        }

        $json = $binary ? null : json_decode($raw, true);

        return [
            'ok' => $code >= 200 && $code < 300,
            'code' => $code,
            'body' => $raw,
            'json' => is_array($json) ? $json : null,
            'url' => $url,
        ];
    }

    protected function responseIsTransientFailure(mixed $failed, int $code): bool
    {
        if ($failed === true && $code <= 0) {
            return true;
        }

        return $code <= 0 || ($code >= 500 && $code < 600) || $code === 408 || $code === 429;
    }

    /** @param  array<string, mixed>  $r */
    public function apiHttpOk(array $r): bool
    {
        return ! empty($r['ok']) || (isset($r['code']) && (int) $r['code'] >= 200 && (int) $r['code'] < 300);
    }

    public function responseIsSuccess(mixed $res): bool
    {
        if (! is_array($res)) {
            return false;
        }
        if (isset($res['ok'])) {
            return (bool) $res['ok'];
        }
        if (isset($res['code'])) {
            $c = (int) $res['code'];

            return $c >= 200 && $c < 300;
        }

        return false;
    }

    public function panelJsonMsg(mixed $json): string
    {
        if (! is_array($json)) {
            return '';
        }
        if (isset($json['detail'])) {
            if (is_string($json['detail'])) {
                return $json['detail'];
            }
            if (is_array($json['detail'])) {
                $parts = [];
                foreach ($json['detail'] as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $parts[] = trim($item);
                        continue;
                    }
                    if (! is_array($item)) {
                        continue;
                    }
                    $loc = '';
                    if (isset($item['loc']) && is_array($item['loc'])) {
                        $locParts = [];
                        foreach ($item['loc'] as $seg) {
                            if (is_scalar($seg) && (string) $seg !== '') {
                                $locParts[] = (string) $seg;
                            }
                        }
                        $loc = implode('.', $locParts);
                    }
                    $msg = trim((string) ($item['msg'] ?? $item['message'] ?? ''));
                    if ($msg === '' && isset($item['type'])) {
                        $msg = (string) $item['type'];
                    }
                    if ($loc !== '' && $msg !== '') {
                        $parts[] = $loc.': '.$msg;
                    } elseif ($msg !== '') {
                        $parts[] = $msg;
                    }
                }
                if ($parts !== []) {
                    return implode('; ', $parts);
                }
            }
        }

        return (string) ($json['message'] ?? $json['msg'] ?? '');
    }

    /** @param  array<string, mixed>  $result */
    protected function mutationResult(array $result): array
    {
        if (! isset($result['detail'])) {
            $msg = $this->panelJsonMsg($result['json'] ?? null);
            if ($msg !== '') {
                $result['detail'] = $msg;
            }
        }

        return $result;
    }

    /** @param  array<string, mixed>  $panel */
    public function testConnection(array $panel): array
    {
        $panelId = (int) ($panel['id'] ?? 0);
        if ($panelId < 1 && ! empty($panel['panel_url'])) {
            $panelId = (int) DB::table('svp_panels')->where('panel_url', $panel['panel_url'])->value('id');
        }

        return $this->runWithPanel($panelId, function () use ($panel, $panelId) {
            if ($panelId > 0) {
                $this->ctx->bind($panelId, $panel);
            }
            $logged = $this->loginWithRetries(3, 200000);
            if (! $logged) {
                return [
                    'ok' => false,
                    'message' => 'login_fail',
                    'flavor' => self::FLAVOR,
                    'provider' => self::PROVIDER,
                    'auth_diag' => $this->ctx->lastAuthDiag,
                ];
            }
            $r = $this->request('system/stats', 'GET');
            $groups = $this->request('groups/simple?all=true', 'GET');

            return [
                'ok' => $this->apiHttpOk($r) || $this->apiHttpOk($groups),
                'flavor' => self::FLAVOR,
                'provider' => self::PROVIDER,
                'status' => (int) ($r['code'] ?? 0),
                'auth_flow' => $this->ctx->lastAuthFlow,
            ];
        }, $panel);
    }

    /** @return array<int, array<string, mixed>>|null */
    public function inboundsList(): ?array
    {
        $r = $this->request('groups/simple?all=true', 'GET');
        if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
            return null;
        }
        $groups = $r['json']['groups'] ?? [];
        if (! is_array($groups)) {
            return null;
        }
        $out = [];
        foreach ($groups as $g) {
            if (! is_array($g)) {
                continue;
            }
            $id = (int) ($g['id'] ?? 0);
            if ($id < 1) {
                continue;
            }
            $name = (string) ($g['name'] ?? ('Group #'.$id));
            $out[] = [
                'id' => $id,
                'remark' => $name,
                'protocol' => 'pasarguard',
                'port' => 0,
                'enable' => true,
                'settings' => json_encode(['group' => true]),
            ];
        }

        return $out;
    }

    /** @return array<int, array<string, mixed>>|null */
    public function groupsListSimple(): ?array
    {
        return $this->inboundsList();
    }

    /** @return array<int, array<string, mixed>>|null */
    public function templatesListSimple(): ?array
    {
        $r = $this->request('user_templates/simple?all=true', 'GET');
        if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
            return null;
        }
        $rows = $r['json']['user_templates'] ?? $r['json']['templates'] ?? [];

        return is_array($rows) ? $rows : null;
    }

    /** @return array<string, mixed>|null */
    public function inboundGet(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $list = $this->inboundsList();
        if (is_array($list)) {
            foreach ($list as $row) {
                if ((int) ($row['id'] ?? 0) === $id) {
                    return $row;
                }
            }
        }
        $r = $this->request('groups/'.$id, 'GET');
        if ($this->apiHttpOk($r) && is_array($r['json'])) {
            $g = $r['json'];

            return [
                'id' => $id,
                'remark' => (string) ($g['name'] ?? ''),
                'protocol' => 'pasarguard',
                'port' => 0,
                'enable' => true,
                'settings' => json_encode($g),
            ];
        }

        return null;
    }

    /** @param  array<string, mixed>  $user */
    public function normalizeUserToClient(array $user): array
    {
        $id = (int) ($user['id'] ?? 0);
        $username = (string) ($user['username'] ?? '');
        $status = strtolower((string) ($user['status'] ?? 'active'));
        $enable = $status !== 'disabled' && $status !== 'on_hold';
        $limit = (int) ($user['data_limit'] ?? 0);
        $used = (int) ($user['used_traffic'] ?? 0);
        $expire = $this->expireToMs($user['expire'] ?? null);
        $subUrl = (string) ($user['subscription_url'] ?? '');
        $subId = $this->subIdFromUrl($subUrl);
        $groupIds = is_array($user['group_ids'] ?? null)
            ? array_values(array_map('intval', $user['group_ids']))
            : [];

        return [
            'id' => (string) $id,
            'email' => $username,
            'enable' => $enable,
            'totalGB' => $limit,
            'up' => 0,
            'down' => $used,
            'total' => $used,
            'expiryTime' => $expire,
            'subId' => $subId,
            'limitIp' => (int) ($user['hwid_limit'] ?? 0),
            'comment' => (string) ($user['note'] ?? ''),
            'remark' => (string) ($user['note'] ?? ''),
            'pg_user_id' => $id,
            'subscription_url' => $subUrl,
            'group_ids' => $groupIds,
            'inboundIds' => $groupIds,
            'inbound_ids' => $groupIds,
        ];
    }

    protected function expireToMs(mixed $expire): int
    {
        if ($expire === null || $expire === '' || $expire === 0) {
            return 0;
        }
        if (is_numeric($expire)) {
            $ts = (int) $expire;
            if ($ts > 9999999999) {
                return $ts;
            }

            return $ts * 1000;
        }
        $ts = strtotime((string) $expire);

        return $ts ? $ts * 1000 : 0;
    }

    public function subIdFromUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $parts = array_values(array_filter(explode('/', trim($path, '/'))));
            if ($parts !== []) {
                return (string) end($parts);
            }
        }

        return substr(md5($url), 0, 16);
    }

    /** @param  array<string, mixed>|null  $client */
    public function pasarguardSubIdForStorage(?array $client, int $panelId = 0): string
    {
        if (! is_array($client)) {
            return '';
        }
        $token = trim((string) ($client['subId'] ?? ''));
        $full = trim((string) ($client['subscription_url'] ?? ''));
        if ($token === '' && $full !== '') {
            $token = $this->subIdFromUrl($full);
        }
        unset($panelId);

        return $token !== '' ? $token : $full;
    }

    /** @return array<string, mixed>|null */
    public function userGetRaw(int|string $key): ?array
    {
        $key = trim((string) $key);
        if ($key === '') {
            return null;
        }
        if (ctype_digit($key)) {
            $r = $this->request('user/by-id/'.(int) $key, 'GET');
        } else {
            $r = $this->request('user/by-username/'.rawurlencode($key), 'GET');
        }
        if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
            return null;
        }

        return $r['json'];
    }

    /** @return array<string, mixed>|null */
    public function clientGetV3(string $email): ?array
    {
        $user = $this->userGetRaw($email);

        return is_array($user) ? $this->normalizeUserToClient($user) : null;
    }

    public function clientExistsV3(string $email): bool
    {
        return $this->clientGetV3($email) !== null;
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<int>  $groupIds
     * @return array{ok:bool, json?:array|null, client?:array|null, code?:int, detail?:string}
     */
    public function userCreate(array $client, array $groupIds, int $templateId = 0): array
    {
        $username = trim((string) ($client['email'] ?? ''));
        if ($username === '') {
            return $this->mutationResult(['ok' => false, 'json' => ['detail' => 'missing_username']]);
        }
        $dataLimit = (int) ($client['totalGB'] ?? 0);
        if ($dataLimit > 0 && $dataLimit < 1073741824) {
            $dataLimit = $dataLimit * 1073741824;
        }
        $expireMs = (int) ($client['expiryTime'] ?? 0);
        $expire = null;
        if ($expireMs > 0) {
            $expire = gmdate('Y-m-d\TH:i:s\Z', (int) floor($expireMs / 1000));
        }
        $body = [
            'username' => $username,
            'status' => ! empty($client['enable']) ? 'active' : 'disabled',
            'data_limit' => max(0, $dataLimit),
            'group_ids' => array_values(array_map('intval', $groupIds)),
            'note' => (string) ($client['comment'] ?? $client['remark'] ?? ''),
            'hwid_limit' => max(0, (int) ($client['limitIp'] ?? 0)),
        ];
        if ($expire !== null) {
            $body['expire'] = $expire;
        }
        if ($templateId < 1 && ! empty($client['panel_template_id'])) {
            $templateId = (int) $client['panel_template_id'];
        }
        $requireTpl = $this->ctx->templateRequired();
        if ($templateId > 0 || $requireTpl) {
            if ($templateId < 1) {
                return $this->mutationResult(['ok' => false, 'json' => ['detail' => 'template_required']]);
            }
            $tplBody = [
                'username' => $username,
                'user_template_id' => $templateId,
                'note' => (string) $body['note'],
            ];
            if (! empty($body['group_ids'])) {
                $tplBody['group_ids'] = $body['group_ids'];
            }
            $r = $this->request('user/from_template', 'POST', $tplBody);
        } else {
            $r = $this->request('user', 'POST', $body);
        }
        if (! $this->apiHttpOk($r)) {
            return $this->mutationResult([
                'ok' => false,
                'json' => is_array($r['json']) ? $r['json'] : null,
                'code' => (int) ($r['code'] ?? 0),
            ]);
        }
        $user = is_array($r['json']) ? $this->normalizeUserToClient($r['json']) : null;

        return ['ok' => true, 'json' => $r['json'], 'client' => $user];
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<int>  $inboundIds
     * @return array{ok:bool, json?:array|null}
     */
    public function clientCreateV3(array $client, array $inboundIds): array
    {
        $out = $this->userCreate($client, $inboundIds, 0);

        return [
            'ok' => ! empty($out['ok']),
            'json' => $out['json'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, code:int, json:array|null, body:string}
     */
    public function addClientRequest(array $payload): array
    {
        $settings = isset($payload['settings']) && is_string($payload['settings'])
            ? json_decode($payload['settings'], true)
            : (isset($payload['settings']) && is_array($payload['settings']) ? $payload['settings'] : []);
        $clients = is_array($settings['clients'] ?? null) ? $settings['clients'] : [];
        $client = $clients !== [] ? (array) $clients[0] : [];
        $gid = (int) ($payload['inboundId'] ?? $payload['inbound_id'] ?? $payload['id'] ?? 0);
        $gids = $gid > 0 ? [$gid] : [];
        $out = $this->userCreate($client, $gids, 0);

        return [
            'ok' => ! empty($out['ok']),
            'code' => (int) ($out['code'] ?? ($out['ok'] ? 200 : 400)),
            'json' => $out['json'] ?? null,
            'body' => '',
        ];
    }

    public function addClientRequestOk(array $requestResult): bool
    {
        return ! empty($requestResult['ok']);
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<int>  $inboundIds
     * @return array{ok:bool, code?:int, json?:array|null, detail?:string}
     */
    public function clientUpdateV3(string $email, array $patch, array $inboundIds = []): array
    {
        unset($inboundIds);
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return ['ok' => false, 'json' => ['detail' => 'user_not_found']];
        }
        $uid = (int) ($user['id'] ?? 0);
        $body = $this->patchToPgBody($patch);
        if ($body === []) {
            return ['ok' => true, 'json' => $user];
        }
        $r = $this->request('user/by-id/'.$uid, 'PUT', $body);

        return $this->mutationResult([
            'ok' => $this->apiHttpOk($r),
            'code' => (int) ($r['code'] ?? 0),
            'json' => is_array($r['json']) ? $r['json'] : null,
        ]);
    }

    /** @param  array<string, mixed>  $patch */
    protected function patchToPgBody(array $patch): array
    {
        $body = [];
        if (array_key_exists('enable', $patch)) {
            $body['status'] = ! empty($patch['enable']) ? 'active' : 'disabled';
        }
        if (array_key_exists('totalGB', $patch)) {
            $v = (int) $patch['totalGB'];
            if ($v > 0 && $v < 1073741824) {
                $v = $v * 1073741824;
            }
            $body['data_limit'] = max(0, $v);
        }
        if (array_key_exists('expiryTime', $patch)) {
            $ms = (int) $patch['expiryTime'];
            $body['expire'] = $ms > 0 ? gmdate('Y-m-d\TH:i:s\Z', (int) floor($ms / 1000)) : null;
        }
        if (array_key_exists('limitIp', $patch)) {
            $body['hwid_limit'] = max(0, (int) $patch['limitIp']);
        }
        if (array_key_exists('comment', $patch) || array_key_exists('remark', $patch)) {
            $body['note'] = (string) ($patch['comment'] ?? $patch['remark'] ?? '');
        }
        if (array_key_exists('group_ids', $patch) && is_array($patch['group_ids'])) {
            $body['group_ids'] = array_values(array_map('intval', $patch['group_ids']));
        }

        return $body;
    }

    /** @return array{ok:bool, json?:array|null} */
    public function clientDeleteV3(string $email, bool $keepTraffic = false): array
    {
        unset($keepTraffic);
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return ['ok' => true];
        }
        $uid = (int) ($user['id'] ?? 0);
        $r = $this->request('user/by-id/'.$uid, 'DELETE');

        return $this->mutationResult([
            'ok' => $this->apiHttpOk($r),
            'json' => is_array($r['json']) ? $r['json'] : null,
        ]);
    }

    /** @return array{ok:bool, subId?:string} */
    public function revokeSubscription(string $email): array
    {
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return ['ok' => false];
        }
        $uid = (int) ($user['id'] ?? 0);
        $r = $this->request('user/by-id/'.$uid.'/revoke_sub', 'POST', []);
        if (! $this->apiHttpOk($r)) {
            return ['ok' => false];
        }
        $fresh = $this->userGetRaw($email);
        $sub = is_array($fresh) ? $this->subIdFromUrl((string) ($fresh['subscription_url'] ?? '')) : '';

        return ['ok' => true, 'subId' => $sub];
    }

    /** @return array{ok:bool, json?:array|null}|null */
    public function delClient(int $inboundId, string $clientId, string $emailFallback = ''): ?array
    {
        unset($inboundId);
        $key = trim($clientId) !== '' ? $clientId : $emailFallback;
        if (ctype_digit($key)) {
            $r = $this->request('user/by-id/'.(int) $key, 'DELETE');

            return ['ok' => $this->apiHttpOk($r), 'json' => is_array($r['json']) ? $r['json'] : null];
        }

        return $this->clientDeleteV3($key);
    }

    /** @return array<string, mixed>|null */
    public function inboundClientByEmail(?array $inbound, string $email): ?array
    {
        unset($inbound);

        return $this->clientGetV3($email);
    }

    public function getNewUuid(): ?string
    {
        return null;
    }

    public function isLikelyClientUuid(string $s): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($s));
    }

    public function isLikelyPanelClientId(string $s): bool
    {
        $t = trim($s);

        return $t !== '' && (bool) preg_match('/^\d+$/', $t) && (int) $t > 0;
    }

    /**
     * @param  callable(array<string,mixed>&):void  $mutator
     * @param  array<string, mixed>  $opts
     * @return array{ok:bool, message:string, client?:array<string,mixed>}
     */
    public function patchPanelClient(int $inboundId, string $email, callable $mutator, array $opts = []): array
    {
        $em = trim($email);
        if ($em === '') {
            return ['ok' => false, 'message' => 'bad_email'];
        }
        $client = $this->clientGetV3($em);
        if (! is_array($client)) {
            return ['ok' => false, 'message' => 'client_not_found'];
        }
        $mutator($client);
        if (! empty($opts['force_enable'])) {
            $client['enable'] = true;
        }
        $res = $this->clientUpdateV3($em, $client, $inboundId > 0 ? [$inboundId] : []);
        if (! $this->responseIsSuccess($res)) {
            return ['ok' => false, 'message' => 'panel_update_failed', 'detail' => $this->panelJsonMsg($res['json'] ?? null)];
        }

        return ['ok' => true, 'message' => 'ok', 'client' => $client];
    }

    /**
     * @param  array<string, mixed>  $svc
     * @return array{ok:bool, message:string, action?:string}
     */
    public function syncServiceRowToPanel(array $svc): array
    {
        $email = trim((string) ($svc['email'] ?? ''));
        $iid = (int) ($svc['inbound_id'] ?? 0);
        if ($email === '' || $iid < 1) {
            return ['ok' => false, 'message' => 'bad_service_row'];
        }
        $totalBytes = \App\Support\Xui\InboundTraffic::capTrafficBytes((int) ($svc['total_traffic'] ?? 0));
        $expiryMs = 0;
        if (! empty($svc['expires_at'])) {
            $ts = strtotime((string) $svc['expires_at'].' UTC');
            if ($ts > 0) {
                $expiryMs = $ts * 1000;
            }
        }
        $limitIp = (int) ($svc['limit_ip'] ?? $svc['panel_limit_ip'] ?? 0);
        if ($limitIp < 1) {
            $limitIp = max(0, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
        }
        $enable = ! isset($svc['client_enabled']) || (int) $svc['client_enabled'] !== 0;
        $remark = trim((string) ($svc['remark'] ?? ''));

        return $this->patchPanelClient($iid, $email, function (array &$cl) use ($totalBytes, $expiryMs, $limitIp, $enable, $remark) {
            $cl['totalGB'] = \App\Support\Xui\InboundTraffic::panelClientTotalgbJsonValue($totalBytes);
            $cl['expiryTime'] = $expiryMs;
            $cl['limitIp'] = $limitIp;
            $cl['enable'] = $enable;
            if ($remark !== '') {
                $cl['remark'] = $remark;
                $cl['comment'] = $remark;
            }
        }, ['force_enable' => $enable]);
    }

    /**
     * @param  array<string, mixed>  $panel
     * @return array{ok:bool, message?:string, action?:string}
     */
    public function syncService(array $panel, int $serviceId): array
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $svc) {
            return ['ok' => false, 'message' => 'not_found'];
        }
        $panelId = (int) ($svc->panel_id ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'panel_not_found'];
        }

        return $this->runWithPanel($panelId, function () use ($svc) {
            if (! $this->loginWithRetries()) {
                return ['ok' => false, 'message' => 'login_fail'];
            }

            return $this->syncServiceRowToPanel((array) $svc);
        }, $panel);
    }

    public function refreshInbound(array $panel, int $serviceId): array
    {
        return $this->syncService($panel, $serviceId);
    }

    /** @param  array<string, mixed>  $panel */
    public function deleteClient(array $panel, int $serviceId): array
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $svc) {
            return ['ok' => false, 'message' => 'not_found'];
        }
        $panelId = (int) ($svc->panel_id ?? 0);

        return $this->runWithPanel($panelId, function () use ($svc) {
            if ($this->loginWithRetries()) {
                $this->delClient(
                    (int) $svc->inbound_id,
                    (string) ($svc->xui_client_uuid ?? $svc->xui_client_id ?? ''),
                    (string) $svc->email
                );
            }
            DB::table('svp_services')->where('id', $svc->id)->update([
                'xui_client_id' => null,
                'xui_client_uuid' => null,
                'deleted_at' => now(),
            ]);

            return ['ok' => true];
        }, $panel);
    }

    public function removePanelClientOnly(int $serviceId): array
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return ['ok' => false, 'message' => 'not_found'];
        }
        $panelId = (int) ($svc->panel_id ?? 0);
        if ($panelId < 1 || (int) ($svc->inbound_id ?? 0) < 1) {
            return ['ok' => true];
        }

        return $this->runWithPanel($panelId, function () use ($svc) {
            if (! $this->loginWithRetries()) {
                return ['ok' => false, 'message' => 'login_fail'];
            }
            $this->delClient(
                (int) $svc->inbound_id,
                (string) ($svc->xui_client_uuid ?? $svc->xui_client_id ?? ''),
                (string) $svc->email
            );
            DB::table('svp_services')->where('id', (int) $svc->id)->update([
                'xui_client_id' => null,
                'xui_client_uuid' => null,
            ]);

            return ['ok' => true];
        });
    }

    /** @return array{ok:bool, json:?array, error:string} */
    public function fetchOnlines(): array
    {
        $r = $this->request('users?status=active&limit=500&offset=0', 'GET');
        if ($this->apiHttpOk($r) && is_array($r['json'])) {
            $users = $r['json']['users'] ?? $r['json']['items'] ?? [];
            $obj = [];
            if (is_array($users)) {
                foreach ($users as $u) {
                    if (! is_array($u)) {
                        continue;
                    }
                    $online = ! empty($u['online']) || ! empty($u['is_online']);
                    if (! $online) {
                        continue;
                    }
                    $name = trim((string) ($u['username'] ?? $u['email'] ?? ''));
                    if ($name !== '') {
                        $obj[] = ['email' => $name, 'username' => $name];
                    }
                }
            }

            return ['ok' => true, 'json' => ['obj' => $obj], 'error' => ''];
        }

        return ['ok' => false, 'json' => null, 'error' => 'onlines_failed'];
    }

    public function onlines(): ?array
    {
        $fetch = $this->fetchOnlines();

        return ! empty($fetch['ok']) ? $fetch['json'] : null;
    }

    /** @return array<int, string> */
    public function parseOnlinesResponse(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $obj = $json['obj'] ?? $json;
        if (! is_array($obj)) {
            return [];
        }
        $out = [];
        foreach ($obj as $item) {
            if (is_string($item)) {
                $out[] = $item;
            } elseif (is_array($item) && isset($item['email'])) {
                $out[] = (string) $item['email'];
            } elseif (is_array($item) && isset($item['username'])) {
                $out[] = (string) $item['username'];
            }
        }

        return array_values(array_unique($out));
    }

    public function countOnlinesResponse(mixed $json): int
    {
        return count($this->parseOnlinesResponse($json));
    }

    /** @return array<int, string> */
    public function clientLinksV3(string $email): array
    {
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return [];
        }
        $url = (string) ($user['subscription_url'] ?? '');

        return $url !== '' ? [$url] : [];
    }

    public function context(): PasarGuardPanelContext
    {
        return $this->ctx;
    }

    /** @return array<int, array<string, mixed>>|null */
    public function groupsSimple(bool $all = true): ?array
    {
        $path = 'groups/simple'.($all ? '?all=true' : '');
        $r = $this->request($path, 'GET');
        if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
            return null;
        }
        $groups = $r['json']['groups'] ?? [];

        return is_array($groups) ? $groups : null;
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkResetTraffic(array $emails): array
    {
        $list = array_values(array_unique(array_filter(array_map(
            static fn ($e) => trim((string) $e),
            $emails
        ), static fn ($e) => $e !== '')));
        if ($list === []) {
            return ['ok' => false, 'detail' => 'no_emails'];
        }
        foreach (array_chunk($list, 50) as $chunk) {
            $r = $this->request('users/bulk/reset', 'POST', ['usernames' => $chunk]);
            if (! $this->apiHttpOk($r)) {
                return $this->mutationResult([
                    'ok' => false,
                    'json' => is_array($r['json']) ? $r['json'] : null,
                    'code' => (int) ($r['code'] ?? 0),
                ]);
            }
        }

        return ['ok' => true];
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkAdjust(array $emails, int $addDays = 0, int $addBytes = 0): array
    {
        $list = array_values(array_unique(array_filter(array_map(
            static fn ($e) => trim((string) $e),
            $emails
        ), static fn ($e) => $e !== '')));
        if ($list === []) {
            return ['ok' => false, 'detail' => 'no_emails'];
        }
        $body = ['usernames' => $list];
        if ($addDays > 0) {
            $body['expire_days'] = $addDays;
        }
        if ($addBytes > 0) {
            $body['data_limit'] = $addBytes;
        }
        $r = $this->request('users/bulk', 'POST', $body);

        return $this->mutationResult([
            'ok' => $this->apiHttpOk($r),
            'json' => is_array($r['json']) ? $r['json'] : null,
            'code' => (int) ($r['code'] ?? 0),
        ]);
    }

    /** @return array<string, mixed> */
    public function exportUsersSnapshot(): array
    {
        $page = 1;
        $all = [];
        $total = 0;
        while ($page < 500) {
            $r = $this->request('users?limit=500&offset='.(($page - 1) * 500), 'GET');
            if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
                break;
            }
            $users = $r['json']['users'] ?? $r['json']['items'] ?? [];
            if (! is_array($users) || $users === []) {
                break;
            }
            foreach ($users as $u) {
                if (is_array($u)) {
                    $all[] = $u;
                }
            }
            $total = (int) ($r['json']['total'] ?? count($all));
            if (count($users) < 500) {
                break;
            }
            $page++;
        }

        return [
            'provider' => self::PROVIDER,
            'total' => $total,
            'users' => $all,
            'exported' => gmdate('c'),
        ];
    }

    /** @return array<int, array<string, mixed>>|null */
    public function clientsListPagedV3(int $page = 1, int $pageSize = 500): ?array
    {
        $offset = max(0, ($page - 1) * $pageSize);
        $r = $this->request('users?limit='.$pageSize.'&offset='.$offset, 'GET');
        if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
            return null;
        }
        $users = $r['json']['users'] ?? $r['json']['items'] ?? [];
        $clients = [];
        if (is_array($users)) {
            foreach ($users as $u) {
                if (is_array($u)) {
                    $clients[] = $this->normalizeUserToClient($u);
                }
            }
        }

        return [
            'clients' => $clients,
            'total' => (int) ($r['json']['total'] ?? count($clients)),
            'has_more' => count($clients) >= $pageSize,
        ];
    }

    public function getClientTraffics(string $email): ?array
    {
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return null;
        }
        $used = (int) ($user['used_traffic'] ?? 0);
        $limit = (int) ($user['data_limit'] ?? 0);

        return [
            'obj' => [
                'up' => 0,
                'down' => $used,
                'total' => $used,
                'totalGB' => $limit,
            ],
        ];
    }

    public function clientAttachV3(string $email, array $inboundIds): ?array
    {
        return $this->clientUpdateV3($email, ['group_ids' => array_values(array_map('intval', $inboundIds))]);
    }

    public function clientDetachV3(string $email, array $inboundIds): ?array
    {
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return ['ok' => false];
        }
        $current = is_array($user['group_ids'] ?? null) ? array_map('intval', $user['group_ids']) : [];
        $remove = array_values(array_map('intval', $inboundIds));
        $next = array_values(array_diff($current, $remove));

        return $this->clientUpdateV3($email, ['group_ids' => $next]);
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int>  $inboundIds
     * @return array{ok:bool}
     */
    public function clientsBulkAttachV3(array $emails, array $inboundIds): array
    {
        $ok = true;
        foreach ($emails as $em) {
            $em = trim((string) $em);
            if ($em === '') {
                continue;
            }
            $res = $this->clientAttachV3($em, $inboundIds);
            if (empty($res['ok'])) {
                $ok = false;
            }
        }

        return ['ok' => $ok];
    }

    /**
     * @param  array<int, string>  $emails
     * @param  array<int>  $inboundIds
     * @return array{ok:bool}
     */
    public function clientsBulkDetachV3(array $emails, array $inboundIds): array
    {
        $ok = true;
        foreach ($emails as $em) {
            $em = trim((string) $em);
            if ($em === '') {
                continue;
            }
            $res = $this->clientDetachV3($em, $inboundIds);
            if (empty($res['ok'])) {
                $ok = false;
            }
        }

        return ['ok' => $ok];
    }

    /** @return array<string, mixed>|null */
    public function getConfigJson(): ?array
    {
        $r = $this->request('settings', 'GET');

        return $this->apiHttpOk($r) && is_array($r['json']) ? $r['json'] : null;
    }

    /** @return array<string, mixed> */
    public function clientIps(string $email): array
    {
        unset($email);

        return ['ok' => false, 'msg' => 'ips_not_supported', 'obj' => []];
    }

    /** @return array{ok:bool} */
    public function clearClientIps(string $email): array
    {
        unset($email);

        return ['ok' => true];
    }

    /** @return array{ok:bool} */
    public function clientClearIpsV3(string $email): array
    {
        return $this->clearClientIps($email);
    }

    /** @return array<int, string> */
    public function parseClientIpsResponse(mixed $json, int $max = 30): array
    {
        unset($json, $max);

        return [];
    }

    /** @return array{ok:bool, msg?:string} */
    public function clientsDelDepletedV3(): array
    {
        return ['ok' => false, 'msg' => 'del_depleted_not_supported'];
    }

    /** @return array{ok:bool, msg?:string} */
    public function clientsDelOrphansV3(): array
    {
        return ['ok' => false, 'msg' => 'del_orphans_not_supported'];
    }

    /** @return array{ok:bool} */
    public function clientsResetAllTrafficV3(): array
    {
        $page = 1;
        $ok = true;
        while ($page < 100) {
            $batch = $this->clientsListPagedV3($page, 200);
            if (! is_array($batch) || empty($batch['clients'])) {
                break;
            }
            foreach ($batch['clients'] as $cl) {
                $em = (string) ($cl['email'] ?? '');
                $res = $this->clientResetTrafficV3($em);
                if (empty($res['ok'])) {
                    $ok = false;
                }
            }
            if (empty($batch['has_more'])) {
                break;
            }
            $page++;
        }

        return ['ok' => $ok];
    }

    /** @return array{ok:bool, json?:array|null} */
    public function clientResetTrafficV3(string $email): array
    {
        $user = $this->userGetRaw($email);
        if (! is_array($user)) {
            return ['ok' => false];
        }
        $uid = (int) ($user['id'] ?? 0);
        $r = $this->request('user/by-id/'.$uid.'/reset', 'POST', []);

        return ['ok' => $this->apiHttpOk($r), 'json' => is_array($r['json']) ? $r['json'] : null];
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkResetTrafficV3(array $emails): array
    {
        $ok = true;
        foreach ($emails as $em) {
            $res = $this->clientResetTrafficV3((string) $em);
            if (empty($res['ok'])) {
                $ok = false;
            }
        }

        return ['ok' => $ok];
    }

    /** @param  array<int, string>  $emails */
    public function resetClientsTrafficBulk(array $emails): array
    {
        $res = $this->clientsBulkResetTrafficV3($emails);

        return [
            'ok' => ! empty($res['ok']),
            'count' => count($emails),
        ];
    }

    public function resetClientTraffic(int $inboundId, string $email): array
    {
        unset($inboundId);

        return $this->clientResetTrafficV3($email);
    }

    /** @return array<string, mixed>|null */
    public function clientTrafficV3(string $email): ?array
    {
        return $this->getClientTraffics($email);
    }

    /** @return array<int, string> */
    public function clientSubLinksV3(string $subId): array
    {
        $sid = trim($subId);
        if ($sid === '') {
            return [];
        }
        $root = rtrim($this->ctx->panelRoot(), '/');
        if ($root === '') {
            return [];
        }

        return [$root.'/sub/'.rawurlencode($sid)];
    }

    /** @return array<int, string> */
    public function clientExternalLinksV3(string $email): array
    {
        return $this->clientLinksV3($email);
    }

    /**
     * @return array{id:string, subId:string}|null
     */
    public function fetchClientIdentityFromPanel(string $email, int $inboundId = 0): ?array
    {
        unset($inboundId);
        $cl = $this->clientGetV3($email);
        if (! is_array($cl)) {
            return null;
        }
        $ident = $this->extractClientIdentityFromRow($cl);
        if ($ident['id'] === '' && $ident['subId'] === '') {
            return null;
        }

        return $ident;
    }

    /**
     * @param  array<string, mixed>  $client
     * @return array{id:string, subId:string}
     */
    public function extractClientIdentityFromRow(array $client): array
    {
        return [
            'id' => trim((string) ($client['id'] ?? $client['uuid'] ?? '')),
            'subId' => trim((string) ($client['subId'] ?? '')),
        ];
    }

    /** @param  array<string, mixed>  $client */
    public function ensureClientPanelId(array &$client): bool
    {
        if (empty($client['id']) && ! empty($client['email'])) {
            $cl = $this->clientGetV3((string) $client['email']);
            if (is_array($cl) && ! empty($cl['id'])) {
                $client['id'] = (string) $cl['id'];

                return true;
            }

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @return string
     */
    public function resolveClientKeyForUpdate(string $dbId, array $inbound, string $email): string
    {
        unset($inbound);
        if (trim($dbId) !== '') {
            return $dbId;
        }
        $cl = $this->clientGetV3($email);

        return is_array($cl) ? (string) ($cl['id'] ?? $email) : $email;
    }

    /** @return array<int, array<string, mixed>> */
    public function clientsForInboundId(int $inboundId): array
    {
        $inboundId = (int) $inboundId;
        $page = 1;
        $out = [];
        while ($page < 100) {
            $batch = $this->clientsListPagedV3($page, 500);
            if (! is_array($batch) || empty($batch['clients'])) {
                break;
            }
            foreach ($batch['clients'] as $cl) {
                $gids = is_array($cl['group_ids'] ?? null) ? array_map('intval', $cl['group_ids']) : [];
                if ($gids === [] || in_array($inboundId, $gids, true)) {
                    $out[] = $cl;
                }
            }
            if (empty($batch['has_more'])) {
                break;
            }
            $page++;
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function serverStatus(): ?array
    {
        $r = $this->request('system/stats', 'GET');
        if (! $this->apiHttpOk($r) || ! is_array($r['json'])) {
            return null;
        }
        $j = $r['json'];

        return [
            'cpu' => (float) ($j['cpu_usage'] ?? $j['cpu'] ?? 0),
            'mem' => (float) ($j['mem_usage'] ?? $j['mem'] ?? 0),
            'uptime' => (int) ($j['uptime'] ?? 0),
            'online' => (int) ($j['online_users'] ?? 0),
            'raw' => $j,
        ];
    }

    /** @return array<int, string> */
    public function probeAlertDetailLines(): array
    {
        $c = $this->ctx->credentials();
        $root = rtrim(trim((string) ($c['panel_url'] ?? '')), '/');
        $host = $root !== '' ? (string) parse_url($root.'/', PHP_URL_HOST) : '';
        $bid = $this->ctx->panelId;
        $auth = $this->ctx->lastAuthDiag;

        $lines = [];
        if ($bid > 0) {
            $lines[] = '🆔 Panel DB id: '.$bid;
        }
        $lines[] = '🛡 Panel type: PasarGuard v5';
        if ($host !== '') {
            $lines[] = '🌐 Host: '.$host;
        }
        if ($root !== '') {
            $lines[] = '🔗 Panel URL: '.$root;
        }
        $loginUrl = (string) ($auth['login_url'] ?? '');
        if ($loginUrl !== '') {
            $loginCode = (int) ($auth['login_http_code'] ?? 0);
            $lines[] = '🔐 JWT login: POST '.$loginUrl.($loginCode > 0 ? ' → HTTP '.$loginCode : '');
        }
        $bodyMsg = trim((string) ($auth['login_body_msg'] ?? ''));
        if ($bodyMsg !== '') {
            $lines[] = '📝 Login response: '.$bodyMsg;
        }

        return $lines;
    }

    /** @param  array<string, mixed>  $inbound */
    public function inboundUpdate(array $inbound): ?array
    {
        unset($inbound);

        return ['ok' => false, 'msg' => 'inbound_update_not_supported'];
    }

    public function inboundDelete(int $id): ?array
    {
        unset($id);

        return ['ok' => false, 'msg' => 'inbound_delete_not_supported'];
    }

    /** @return array{ok:bool} */
    public function importDbFromPath(string $dbPath): array
    {
        unset($dbPath);

        return ['ok' => false];
    }

    /** @return string|false */
    public function getDbBinary(): string|false
    {
        return false;
    }

    /** @return string|false */
    public function getDbBinaryForBackup(): string|false
    {
        return false;
    }

    /** @return array{ok:bool, url?:string} */
    public function probeGetDb(): array
    {
        return ['ok' => false, 'url' => ''];
    }

    /** @return string|false */
    public function getDbBinaryWithRetries(int $attempts = 3, bool $forBackup = false): string|false
    {
        unset($attempts, $forBackup);

        return false;
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkAdjustV3(array $emails, int $addDays = 0, int $addBytes = 0): array
    {
        return $this->clientsBulkAdjust($emails, $addDays, $addBytes);
    }
}
