<?php

namespace App\Modules\XuiPanel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class XuiClient
{
    protected XuiPanelContext $ctx;

    protected XuiHttpTransport $http;

    protected XuiSessionStore $sessions;

    public function __construct(
        ?XuiPanelContext $ctx = null,
        ?XuiSessionStore $sessions = null,
    ) {
        $this->sessions = $sessions ?? new XuiSessionStore;
        $this->ctx = $ctx ?? new XuiPanelContext;
        $this->http = new XuiHttpTransport($this->ctx, $this->sessions);
    }

    /** @param  array<string, mixed>  $panel */
    public function runWithPanel(int $panelId, callable $fn, array $panel = []): mixed
    {
        $prevCtx = $this->ctx;
        $prevHttp = $this->http;
        $this->ctx = new XuiPanelContext;
        $this->ctx->bind($panelId, $panel);
        $this->http = new XuiHttpTransport($this->ctx, $this->sessions);
        try {
            return $fn($this);
        } finally {
            $this->ctx = $prevCtx;
            $this->http = $prevHttp;
        }
    }

    public function clearSession(): void
    {
        $this->http->clearSession();
    }

    public function ensureReady(bool $forceReauth = false, bool $cookieOnly = false): bool
    {
        return $this->http->ensureReady($forceReauth, $cookieOnly);
    }

    /** @return array{login_attempts:int, session_reuse:int, probes:int, panel_id:int} */
    public function getSessionStats(): array
    {
        return $this->http->getSessionStats();
    }

    public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool
    {
        return $this->http->loginWithRetries($maxAttempts, $delayUs);
    }

    /** @return array<string, mixed>|null */
    public function serverStatus(): ?array
    {
        $r = $this->http->request('server/status', 'GET');

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    public function isV3ClientsApi(): bool
    {
        $this->http->getApiFlavor();

        return $this->ctx->isV3ClientsApi();
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
                return ['ok' => false, 'message' => 'login_fail', 'flavor' => $this->ctx->getApiFlavor()];
            }
            $r = $this->http->request('server/status', 'GET');
            $flavor = $this->http->detectApiFlavor();

            return [
                'ok' => $this->http->apiHttpOk($r),
                'flavor' => $flavor,
                'status' => (int) ($r['code'] ?? 0),
                'auth_flow' => $this->ctx->lastAuthFlow,
            ];
        });
    }

    /** @return array<string, mixed>|null */
    public function inboundGet(int $id): ?array
    {
        $r = $this->http->request('inbounds/get/'.$id, 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return null;
        }
        $obj = $r['json']['obj'] ?? null;

        return is_array($obj) ? $obj : null;
    }

    /** @return array<int, array<string, mixed>>|null */
    public function inboundsList(): ?array
    {
        $r = $this->http->request('inbounds/list', 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return null;
        }
        $j = $r['json'] ?? null;
        if (! is_array($j)) {
            return null;
        }
        if (isset($j['obj']) && is_array($j['obj'])) {
            return $j['obj'];
        }
        if (isset($j['inbounds']) && is_array($j['inbounds'])) {
            return $j['inbounds'];
        }

        return null;
    }

    /** @return array{ok:bool, json:?array, error:string} */
    public function fetchOnlines(): array
    {
        if ($this->isV3ClientsApi()) {
            $r = $this->http->request('clients/onlines', 'POST', []);
            if ($this->http->apiHttpOk($r)) {
                return ['ok' => true, 'json' => is_array($r['json'] ?? null) ? $r['json'] : null, 'error' => ''];
            }

            return ['ok' => false, 'json' => null, 'error' => 'clients_onlines_failed'];
        }
        $r = $this->http->request('inbounds/onlines', 'POST', []);
        if ($this->http->apiHttpOk($r)) {
            return ['ok' => true, 'json' => is_array($r['json'] ?? null) ? $r['json'] : null, 'error' => ''];
        }
        $code = (int) ($r['code'] ?? 0);
        if ($code === 404) {
            $rV3 = $this->http->request('clients/onlines', 'POST', []);
            if ($this->http->apiHttpOk($rV3)) {
                $this->http->detectApiFlavor();

                return ['ok' => true, 'json' => is_array($rV3['json'] ?? null) ? $rV3['json'] : null, 'error' => ''];
            }
        }

        return ['ok' => false, 'json' => null, 'error' => $code === 404 ? 'inbounds_onlines_not_found' : 'onlines_failed'];
    }

    public function onlines(): ?array
    {
        $fetch = $this->fetchOnlines();

        return ! empty($fetch['ok']) ? $fetch['json'] : null;
    }

    public function clientIps(string $email): ?array
    {
        $email = trim($email);
        if ($email === '') {
            return null;
        }
        if ($this->isV3ClientsApi()) {
            return $this->clientIpsV3($email);
        }
        $r = $this->http->request('inbounds/clientIps/'.rawurlencode($email), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientIpsV3(string $email): ?array
    {
        $em = trim($email);
        if ($em === '') {
            return null;
        }
        $r = $this->http->request('clients/ips/'.rawurlencode($em), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clearClientIps(string $email): ?array
    {
        if ($this->isV3ClientsApi()) {
            return $this->clientClearIpsV3($email);
        }
        $r = $this->http->request('inbounds/clearClientIps/'.rawurlencode(trim($email)), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientClearIpsV3(string $email): ?array
    {
        $em = trim($email);
        if ($em === '') {
            return null;
        }
        $r = $this->http->request('clients/clearIps/'.rawurlencode($em), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    public function clientExistsV3(string $email): bool
    {
        return $this->clientGetV3($email) !== null;
    }

    /** @return array<int, string> */
    public function parseClientIpsResponse(mixed $json, int $max = 30): array
    {
        $lim = max(1, min(100, $max));
        if (! is_array($json)) {
            return [];
        }
        $obj = array_key_exists('obj', $json) ? $json['obj'] : $json;
        $ips = [];
        if (is_string($obj) && $obj !== '' && $obj !== 'No IP Record') {
            $decoded = json_decode($obj, true);
            $ips = is_array($decoded) ? $decoded : preg_split('/[\s,]+/', $obj);
        } elseif (is_array($obj)) {
            $mapKeysAreIps = false;
            foreach ($obj as $key => $item) {
                if (is_string($key) && $this->isValidClientIpString(trim($key))) {
                    $mapKeysAreIps = true;
                    break;
                }
            }
            if ($mapKeysAreIps) {
                foreach ($obj as $key => $item) {
                    if (is_string($key)) {
                        $k = trim($key);
                        if ($this->isValidClientIpString($k)) {
                            $ips[] = $k;
                        }
                    }
                }
            } else {
                foreach ($obj as $item) {
                    if (is_string($item) && trim($item) !== '') {
                        $ips[] = trim($item);
                    } elseif (is_array($item)) {
                        if (! empty($item['ip'])) {
                            $ips[] = trim((string) $item['ip']);
                        } elseif (! empty($item['Ip'])) {
                            $ips[] = trim((string) $item['Ip']);
                        }
                    }
                }
                if ($ips === []) {
                    $ips = $obj;
                }
            }
        }

        return array_slice(
            array_values(array_unique(array_filter(array_map('trim', array_map('strval', (array) $ips)), fn ($ip) => $ip !== '' && $ip !== 'No IP Record' && $this->isValidClientIpString($ip)))),
            0,
            $lim
        );
    }

    public function isValidClientIpString(string $ip): bool
    {
        return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    /** @return array<int, string> */
    public function parseOnlinesResponse(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $arr = null;
        if (isset($json['obj']) && is_array($json['obj'])) {
            $arr = $json['obj'];
        } elseif (isset($json['obj']) && is_string($json['obj']) && trim($json['obj']) !== '') {
            $decoded = json_decode($json['obj'], true);
            $arr = is_array($decoded) ? $decoded : null;
        } elseif (isset($json['data']) && is_array($json['data'])) {
            $arr = $json['data'];
        } elseif (array_values($json) === $json) {
            $arr = $json;
        }
        if (! is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $v) {
            if (is_string($v) && trim($v) !== '') {
                $out[] = trim($v);
            } elseif (is_array($v) && ! empty($v['email'])) {
                $out[] = trim((string) $v['email']);
            }
        }

        return array_values(array_unique($out));
    }

    public function countOnlinesResponse(mixed $json): int
    {
        return count($this->parseOnlinesResponse($json));
    }

    public function getNewUuid(): ?string
    {
        $r = $this->http->request('server/getNewUUID', 'GET');
        if (is_array($r['json'] ?? null) && ! empty($r['json']['obj'])) {
            $u = $this->parseUuidValue($r['json']['obj']);
            if (is_string($u)) {
                return $u;
            }
        }

        return (string) Str::uuid();
    }

    public function isLikelyClientUuid(string $s): bool
    {
        return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', trim($s));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok:bool, code:int, json:array|null, body:string}
     */
    public function addClientRequest(array $payload): array
    {
        if ($this->isV3ClientsApi()) {
            $client = $this->extractClientFromLegacyAddPayload($payload);
            $iid = (int) ($payload['id'] ?? 0);
            $r = $this->http->request('clients/add', 'POST', [
                'client' => $this->normalizeClientForV3($client),
                'inboundIds' => $iid > 0 ? [$iid] : [],
            ]);

            return [
                'ok' => ! empty($r['ok']) && $this->http->responseIsSuccess($r['json'] ?? null),
                'code' => (int) ($r['code'] ?? 0),
                'json' => is_array($r['json'] ?? null) ? $r['json'] : null,
                'body' => (string) ($r['body'] ?? ''),
            ];
        }
        $r = $this->http->request('inbounds/addClient', 'POST', $payload);

        return [
            'ok' => ! empty($r['ok']),
            'code' => (int) ($r['code'] ?? 0),
            'json' => is_array($r['json'] ?? null) ? $r['json'] : null,
            'body' => (string) ($r['body'] ?? ''),
        ];
    }

    public function addClientRequestOk(array $requestResult): bool
    {
        if (empty($requestResult['ok'])) {
            return false;
        }

        return $this->http->responseIsSuccess($requestResult['json'] ?? null);
    }

    public function panelJsonMsg(mixed $json): string
    {
        return is_array($json) ? trim((string) ($json['msg'] ?? '')) : '';
    }

    public function responseIsSuccess(mixed $res): bool
    {
        return $this->http->responseIsSuccess($res);
    }

    /** @return array<string, mixed>|null */
    public function inboundClientByEmail(?array $inbound, string $email): ?array
    {
        $want = trim($email);
        if ($want === '') {
            return null;
        }
        if ($this->isV3ClientsApi()) {
            return $this->clientGetV3($want);
        }
        if (! is_array($inbound)) {
            return null;
        }
        $settings = $inbound['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'])) {
            return null;
        }
        foreach ($dec['clients'] as $c) {
            if (is_array($c) && isset($c['email']) && (string) $c['email'] === $want) {
                return $c;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function clientGetV3(string $email): ?array
    {
        $em = trim($email);
        if ($em === '') {
            return null;
        }
        $r = $this->http->request('clients/get/'.rawurlencode($em), 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return null;
        }
        $j = $r['json'] ?? null;
        if (! is_array($j)) {
            return null;
        }
        if (isset($j['obj']['client']) && is_array($j['obj']['client'])) {
            return $j['obj']['client'];
        }
        if (isset($j['client']) && is_array($j['client'])) {
            return $j['client'];
        }
        if (isset($j['obj']) && is_array($j['obj'])) {
            return $j['obj'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $client
     * @param  array<int>  $inboundIds
     * @return array<string, mixed>|null
     */
    public function clientUpdateV3(string $email, array $client, array $inboundIds = []): ?array
    {
        $path = 'clients/update/'.rawurlencode(trim($email));
        $ids = array_values(array_filter(array_map('intval', $inboundIds), fn ($v) => $v > 0));
        if ($ids !== []) {
            $path .= '?inboundIds='.implode(',', $ids);
        }
        $r = $this->http->request($path, 'POST', $this->normalizeClientForV3($client));

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientDeleteV3(string $email): ?array
    {
        $r = $this->http->request('clients/del/'.rawurlencode(trim($email)), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    public function delClient(int $inboundId, string $clientId, string $emailFallback = ''): ?array
    {
        if ($this->isV3ClientsApi()) {
            $em = trim($emailFallback) !== '' ? trim($emailFallback) : trim($clientId);

            return $this->clientDeleteV3($em);
        }
        $r = $this->http->request('inbounds/'.$inboundId.'/delClient/'.rawurlencode($clientId), 'POST', []);
        if ($this->http->responseIsSuccess($r['json'] ?? null)) {
            return $r['json'];
        }
        $em = trim($emailFallback);
        if ($em === '' || $em === $clientId) {
            return is_array($r['json'] ?? null) ? $r['json'] : null;
        }
        $r2 = $this->http->request('inbounds/'.$inboundId.'/delClientByEmail/'.rawurlencode($em), 'POST', []);

        return is_array($r2['json'] ?? null) ? $r2['json'] : null;
    }

    public function resetClientTraffic(int $inboundId, string $email): ?array
    {
        if ($this->isV3ClientsApi()) {
            $r = $this->http->request('clients/resetTraffic/'.rawurlencode(trim($email)), 'POST', []);

            return is_array($r['json'] ?? null) ? $r['json'] : null;
        }
        $r = $this->http->request('inbounds/'.$inboundId.'/resetClientTraffic/'.rawurlencode($email), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
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
        });
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
            }
        }, ['force_enable' => $enable]);
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
        if ($this->isV3ClientsApi()) {
            $client = $this->clientGetV3($em);
            if (! is_array($client)) {
                return ['ok' => false, 'message' => 'client_not_found'];
            }
            $mutator($client);
            if (! empty($opts['force_enable'])) {
                $client['enable'] = true;
            }
            $res = $this->clientUpdateV3($em, $client, [$inboundId]);
            if (! $this->http->responseIsSuccess($res)) {
                return ['ok' => false, 'message' => 'panel_update_failed'];
            }

            return ['ok' => true, 'message' => 'ok', 'client' => $client];
        }
        $inbound = $this->inboundGet($inboundId);
        if (! is_array($inbound)) {
            return ['ok' => false, 'message' => 'inbound_not_found'];
        }
        $settings = $inbound['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'])) {
            return ['ok' => false, 'message' => 'empty_clients'];
        }
        $updated = null;
        foreach ($dec['clients'] as &$cl) {
            if (! is_array($cl) || ! isset($cl['email']) || (string) $cl['email'] !== $em) {
                continue;
            }
            $mutator($cl);
            if (! empty($opts['force_enable'])) {
                $cl['enable'] = true;
            }
            $updated = $cl;
            break;
        }
        unset($cl);
        if (! is_array($updated)) {
            return ['ok' => false, 'message' => 'client_not_found'];
        }
        $clientKey = (string) ($updated['id'] ?? $em);
        $payload = ['id' => $inboundId, 'settings' => json_encode(['clients' => [$updated]])];
        $r = $this->http->request('inbounds/updateClient/'.rawurlencode($clientKey), 'POST', $payload);
        if (! $this->http->responseIsSuccess($r['json'] ?? null)) {
            return ['ok' => false, 'message' => 'panel_update_failed'];
        }

        return ['ok' => true, 'message' => 'ok', 'client' => $updated];
    }

    /** Remove client from panel only (for transfer); does not soft-delete service row. */
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
        });
    }

    /** @param  array<string, mixed>  $panel */
    public function refreshInbound(array $panel, int $serviceId): array
    {
        return $this->syncService($panel, $serviceId);
    }

    public function regenerateKey(int $serviceId): array
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $svc) {
            return ['ok' => false, 'message' => 'not_found'];
        }
        $uuid = $this->runWithPanel((int) $svc->panel_id, function () {
            if (! $this->loginWithRetries()) {
                return null;
            }

            return $this->getNewUuid();
        });
        if (! is_string($uuid) || ! $this->isLikelyClientUuid($uuid)) {
            $uuid = (string) Str::uuid();
        }
        DB::table('svp_services')->where('id', $serviceId)->update(['xui_client_uuid' => $uuid]);
        $this->syncService([], $serviceId);

        return ['ok' => true, 'uuid' => $uuid];
    }

    public function regenerateSubId(int $serviceId): array
    {
        $subId = bin2hex(random_bytes(8));
        DB::table('svp_services')->where('id', $serviceId)->update(['sub_id' => $subId]);
        $this->syncService([], $serviceId);

        return ['ok' => true, 'sub_id' => $subId];
    }

    public function setLimitIp(int $serviceId, int $limit): array
    {
        DB::table('svp_services')->where('id', $serviceId)->update(['limit_ip' => $limit]);
        $this->syncService([], $serviceId);

        return ['ok' => true];
    }

    /**
     * @return array{clients:array<int,array<string,mixed>>,total:int}|null
     */
    public function clientsListPagedV3(int $page = 1, int $pageSize = 500): ?array
    {
        $p = max(1, $page);
        $ps = max(1, min(1000, $pageSize));
        $r = $this->http->request('clients/list/paged?page='.$p.'&pageSize='.$ps, 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return null;
        }
        $j = $r['json'] ?? null;
        if (! is_array($j)) {
            return null;
        }
        $obj = isset($j['obj']) && is_array($j['obj']) ? $j['obj'] : $j;
        $clients = [];
        if (isset($obj['clients']) && is_array($obj['clients'])) {
            $clients = $obj['clients'];
        } elseif (isset($obj['list']) && is_array($obj['list'])) {
            $clients = $obj['list'];
        } elseif (is_array($obj) && isset($obj[0])) {
            $clients = $obj;
        }

        return [
            'clients' => is_array($clients) ? $clients : [],
            'total' => (int) ($obj['total'] ?? count($clients)),
        ];
    }

    /**
     * @param  array<string, mixed>  $client
     * @return array<string, mixed>
     */
    public function normalizeClientForV3(array $client): array
    {
        $out = $client;
        if (! isset($out['comment']) && isset($out['remark']) && trim((string) $out['remark']) !== '') {
            $out['comment'] = (string) $out['remark'];
        }
        unset($out['remark'], $out['up'], $out['down'], $out['total'], $out['lastOnline']);
        if (isset($out['id']) && ! isset($out['uuid']) && $this->isLikelyClientUuid((string) $out['id'])) {
            $out['uuid'] = (string) $out['id'];
        }

        return $out;
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

    /**
     * @return array{id:string, subId:string}|null
     */
    public function fetchClientIdentityFromPanel(string $email, int $inboundId = 0): ?array
    {
        unset($inboundId);
        $client = $this->clientGetV3($email);
        if (! is_array($client)) {
            return null;
        }
        $ident = $this->extractClientIdentityFromRow($client);
        if ($ident['id'] === '' && $ident['subId'] === '') {
            return null;
        }

        return $ident;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function extractClientFromLegacyAddPayload(array $payload): array
    {
        $settings = $payload['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        $clients = is_array($dec) && isset($dec['clients']) && is_array($dec['clients']) ? $dec['clients'] : [];

        return is_array($clients[0] ?? null) ? $clients[0] : [];
    }

    protected function parseUuidValue(mixed $raw): ?string
    {
        if (is_string($raw) && $this->isLikelyClientUuid($raw)) {
            return $raw;
        }
        if (is_array($raw)) {
            foreach (['uuid', 'id', 'obj'] as $k) {
                if (isset($raw[$k]) && is_string($raw[$k]) && $this->isLikelyClientUuid($raw[$k])) {
                    return $raw[$k];
                }
            }
        }

        return null;
    }

    /** @return array<int, string> */
    public function probeAlertDetailLines(): array
    {
        $c = $this->ctx->credentials();
        $root = rtrim(trim((string) ($c['panel_url'] ?? '')), '/');
        $api = trim((string) ($c['panel_api_base'] ?? 'panel/api'), " \t\n\r/") ?: 'panel/api';
        $bid = $this->ctx->panelId;
        $host = $root !== '' ? (string) parse_url($root.'/', PHP_URL_HOST) : '';

        $lines = [];
        if ($bid > 0) {
            $lines[] = '🆔 Panel DB id: '.$bid;
        } else {
            $lines[] = '📂 Source: legacy plugin panel settings';
        }
        if ($host !== '') {
            $lines[] = '🌐 Host: '.$host;
        }
        if ($root !== '') {
            $lines[] = '🔗 Panel URL: '.$root;
        }
        $lines[] = '📡 panel_api_base: '.$api;
        if ($this->ctx->lastAuthFlow !== '') {
            $lines[] = '🔐 auth_flow: '.$this->ctx->lastAuthFlow;
        }

        return $lines;
    }

    protected string $lastGetDbStep = '';

    protected int $lastGetDbHttp = 0;

    /** @param  array<int, string>  $emails */
    protected function normalizeClientEmails(array $emails): array
    {
        return array_values(array_unique(array_filter(array_map(
            static fn ($e) => trim((string) $e),
            $emails
        ), static fn ($e) => $e !== '')));
    }

    /** @return array<string, mixed>|null */
    public function clientAttachV3(string $email, array $inboundIds): ?array
    {
        $em = trim($email);
        $ids = array_values(array_filter(array_map('intval', $inboundIds), fn ($v) => $v > 0));
        if ($em === '' || $ids === []) {
            return ['success' => false, 'msg' => 'bad_params'];
        }
        $r = $this->http->request('clients/'.rawurlencode($em).'/attach', 'POST', ['inboundIds' => $ids]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientDetachV3(string $email, array $inboundIds): ?array
    {
        $em = trim($email);
        $ids = array_values(array_filter(array_map('intval', $inboundIds), fn ($v) => $v > 0));
        if ($em === '' || $ids === []) {
            return ['success' => false, 'msg' => 'bad_params'];
        }
        $r = $this->http->request('clients/'.rawurlencode($em).'/detach', 'POST', ['inboundIds' => $ids]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientCreateV3(array $client, array $inboundIds): ?array
    {
        $ids = array_values(array_filter(array_map('intval', $inboundIds), fn ($v) => $v > 0));
        if ($ids === []) {
            return null;
        }

        $res = $this->addClientRequest([
            'id' => $ids[0],
            'settings' => json_encode(['clients' => [$client]], JSON_UNESCAPED_UNICODE),
        ]);
        if (! $this->addClientRequestOk($res)) {
            return is_array($res['json'] ?? null) ? $res['json'] : null;
        }

        return is_array($res['json'] ?? null) ? $res['json'] : ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    public function clientPatchV3(string $email, array $patch, array $inboundIds = []): ?array
    {
        return $this->clientUpdateV3($email, $patch, $inboundIds);
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkResetTrafficV3(array $emails): ?array
    {
        $list = $this->normalizeClientEmails($emails);
        if ($list === []) {
            return null;
        }
        $r = $this->http->request('clients/bulkResetTraffic', 'POST', ['emails' => $list]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    public function clientsDelDepletedV3(): ?array
    {
        $r = $this->http->request('clients/delDepleted', 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    public function clientsDelOrphansV3(): ?array
    {
        $r = $this->http->request('clients/delOrphans', 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkAttachV3(array $emails, array $inboundIds): ?array
    {
        $list = $this->normalizeClientEmails($emails);
        $ids = array_values(array_filter(array_map('intval', $inboundIds), fn ($v) => $v > 0));
        if ($list === [] || $ids === []) {
            return null;
        }
        $r = $this->http->request('clients/bulkAttach', 'POST', ['emails' => $list, 'inboundIds' => $ids]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkDetachV3(array $emails, array $inboundIds): ?array
    {
        $list = $this->normalizeClientEmails($emails);
        $ids = array_values(array_filter(array_map('intval', $inboundIds), fn ($v) => $v > 0));
        if ($list === [] || $ids === []) {
            return null;
        }
        $r = $this->http->request('clients/bulkDetach', 'POST', ['emails' => $list, 'inboundIds' => $ids]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @param  array<int, string>  $emails */
    public function clientsBulkAdjustV3(array $emails, int $addDays = 0, int $addBytes = 0): ?array
    {
        $list = $this->normalizeClientEmails($emails);
        if ($list === []) {
            return null;
        }
        $body = ['emails' => $list];
        if ($addDays > 0) {
            $body['addDays'] = $addDays;
        }
        if ($addBytes > 0) {
            $body['addBytes'] = $addBytes;
        }
        $r = $this->http->request('clients/bulkAdjust', 'POST', $body);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function clientGroupsListV3(): array
    {
        $r = $this->http->request('clients/groups/list', 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return [];
        }

        return $this->parseGroupsListResponse($r['json'] ?? null);
    }

    /** @return array<int, array<string, mixed>> */
    public function parseGroupsListResponse(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $arr = isset($json['obj']) && is_array($json['obj']) ? $json['obj'] : (array_values($json) === $json ? $json : null);
        if (! is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $row) {
            if (is_array($row)) {
                $out[] = $row;
            } elseif (is_string($row) && trim($row) !== '') {
                $out[] = ['name' => trim($row)];
            }
        }

        return $out;
    }

    /** @param  array<int, string>  $emails */
    public function clientGroupsBulkAddV3(array $emails, string $groupName): ?array
    {
        $list = $this->normalizeClientEmails($emails);
        $grp = trim($groupName);
        if ($list === [] || $grp === '') {
            return ['success' => false, 'msg' => 'emails and group required'];
        }
        $r = $this->http->request('clients/groups/bulkAdd', 'POST', ['emails' => $list, 'group' => $grp]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @param  array<int, string>  $emails */
    public function clientGroupsBulkRemoveV3(array $emails): ?array
    {
        $list = $this->normalizeClientEmails($emails);
        if ($list === []) {
            return ['success' => false, 'msg' => 'emails required'];
        }
        $r = $this->http->request('clients/groups/bulkRemove', 'POST', ['emails' => $list]);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function nodesListV3(): array
    {
        $r = $this->http->request('nodes/list', 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return [];
        }

        return $this->parseNodesListResponse($r['json'] ?? null);
    }

    /** @return array<int, array<string, mixed>> */
    public function parseNodesListResponse(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $arr = isset($json['obj']) && is_array($json['obj']) ? $json['obj'] : (array_values($json) === $json ? $json : null);
        if (! is_array($arr)) {
            return [];
        }
        $out = [];
        foreach ($arr as $row) {
            if (is_array($row)) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @return array<int, string> */
    public function clientLinksV3(string $email): array
    {
        $em = trim($email);
        if ($em === '') {
            return [];
        }
        $r = $this->http->request('clients/links/'.rawurlencode($em), 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return [];
        }

        return $this->parseLinkLines($r['json'] ?? null);
    }

    /** @return array<int, string> */
    public function clientSubLinksV3(string $subId): array
    {
        $sid = trim($subId);
        if ($sid === '') {
            return [];
        }
        $r = $this->http->request('clients/subLinks/'.rawurlencode($sid), 'GET');
        if (! $this->http->apiHttpOk($r)) {
            return [];
        }

        return $this->parseLinkLines($r['json'] ?? null);
    }

    /** @return array<int, string> */
    protected function parseLinkLines(mixed $json): array
    {
        if (! is_array($json)) {
            return [];
        }
        $obj = $json['obj'] ?? $json;
        if (is_string($obj) && $obj !== '') {
            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $obj) ?: [])));
        }
        if (! is_array($obj)) {
            return [];
        }
        $lines = [];
        foreach ($obj as $line) {
            if (is_string($line) && trim($line) !== '') {
                $lines[] = trim($line);
            }
        }

        return $lines;
    }

    /** @return array<string, mixed>|null */
    public function inboundUpdate(array $inbound): ?array
    {
        $id = (int) ($inbound['id'] ?? 0);
        if ($id < 1) {
            return null;
        }
        $r = $this->http->request('inbounds/update/'.$id, 'POST', $inbound);
        if (! $this->http->apiHttpOk($r)) {
            return is_array($r['json'] ?? null) ? $r['json'] : null;
        }

        return is_array($r['json'] ?? null) ? $r['json'] : ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    public function inboundDelete(int $id): ?array
    {
        if ($id < 1) {
            return null;
        }
        $r = $this->http->request('inbounds/del/'.$id, 'POST', ['id' => $id]);
        if (! $this->http->apiHttpOk($r)) {
            return is_array($r['json'] ?? null) ? $r['json'] : null;
        }

        return is_array($r['json'] ?? null) ? $r['json'] : ['ok' => true];
    }

    /** @return array<string, mixed>|null */
    public function getClientTraffics(string $email): ?array
    {
        if ($this->isV3ClientsApi()) {
            return $this->clientTrafficV3($email);
        }
        $r = $this->http->request('inbounds/getClientTraffics/'.rawurlencode(trim($email)), 'GET');

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientTrafficV3(string $email): ?array
    {
        $r = $this->http->request('clients/traffic/'.rawurlencode(trim($email)), 'GET');
        $j = $r['json'] ?? null;

        return is_array($j) ? $j : null;
    }

    public function isSqliteBytes(string $raw): bool
    {
        return strlen($raw) >= 16 && str_starts_with($raw, 'SQLite format 3');
    }

    /** @return string|false */
    protected function parseDbBinaryResponse(array $r): string|false
    {
        $code = (int) ($r['code'] ?? 0);
        $this->lastGetDbHttp = $code;
        $raw = (string) ($r['body'] ?? '');
        if (empty($r['ok']) || $raw === '') {
            $this->lastGetDbStep = in_array($code, [401, 403], true) ? 'auth' : 'http_'.$code;

            return false;
        }
        $trim = ltrim($raw);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $this->lastGetDbStep = 'invalid_response';

            return false;
        }
        if ($trim !== '' && ($trim[0] === '<' || stripos($trim, '<!DOCTYPE') === 0 || stripos($trim, '<html') !== false)) {
            $this->lastGetDbStep = 'invalid_response';

            return false;
        }
        if (! $this->isSqliteBytes($raw)) {
            $this->lastGetDbStep = 'invalid_response';

            return false;
        }
        $this->lastGetDbStep = '';

        return $raw;
    }

    /** @return string|false */
    protected function getDbBinaryInner(): string|false
    {
        $c = $this->ctx->credentials();
        $hasToken = trim((string) ($c['panel_api_token'] ?? '')) !== '';
        $hasCookie = trim((string) ($c['panel_username'] ?? '')) !== ''
            && trim((string) ($c['panel_password'] ?? '')) !== '';
        if (! $hasToken && ! $hasCookie) {
            $this->lastGetDbStep = 'missing_cookie_creds';

            return false;
        }
        if ($hasToken) {
            $r = $this->http->request('server/getDb', 'GET', [], false, 2, true);
            $db = $this->parseDbBinaryResponse($r);
            if ($db !== false) {
                return $db;
            }
        }
        if ($hasCookie) {
            if (! $this->loginWithRetries(3, 350000)) {
                if ($this->lastGetDbStep === '') {
                    $this->lastGetDbStep = 'login';
                }

                return false;
            }
            $r = $this->http->request('server/getDb', 'GET', [], true, 2, true);
            $db = $this->parseDbBinaryResponse($r);
            if ($db !== false) {
                return $db;
            }
        }
        if ($this->lastGetDbStep === '') {
            $this->lastGetDbStep = 'bearer_getdb_failed';
        }

        return false;
    }

    /** @return string|false */
    public function getDbBinary(): string|false
    {
        return $this->getDbBinaryInner();
    }

    /** @return string|false */
    public function getDbBinaryForBackup(): string|false
    {
        $prev = $this->http->setRequestTimeout(max(60, 90));
        try {
            return $this->getDbBinaryInner();
        } finally {
            $this->http->setRequestTimeout($prev);
        }
    }

    /** @return string|false */
    public function getDbBinaryWithRetries(int $attempts = 3, bool $forBackup = false): string|false
    {
        $max = max(1, $attempts);
        for ($i = 0; $i < $max; $i++) {
            $db = $forBackup ? $this->getDbBinaryForBackup() : $this->getDbBinary();
            if ($db !== false && $db !== '') {
                return $db;
            }
            if ($i + 1 < $max) {
                usleep($forBackup ? 250000 + $i * 150000 : 400000 + $i * 300000);
            }
        }

        return false;
    }

    /** @return array{ok:bool, message?:string, code?:int} */
    public function importDbFromPath(string $dbPath): array
    {
        if ($dbPath === '' || ! is_readable($dbPath)) {
            return ['ok' => false, 'message' => 'unreadable_db'];
        }
        if ((int) @filesize($dbPath) < 512) {
            return ['ok' => false, 'message' => 'db_too_small'];
        }
        if (! $this->loginWithRetries()) {
            return ['ok' => false, 'message' => 'login_fail'];
        }

        return $this->http->requestImportDb($dbPath);
    }

    public function lastGetDbStep(): string
    {
        return $this->lastGetDbStep;
    }

    public function lastGetDbHttp(): int
    {
        return $this->lastGetDbHttp;
    }

    /** @param  array<int, string>  $emails */
    public function resetClientsTrafficBulk(array $emails): array
    {
        if ($this->isV3ClientsApi()) {
            $res = $this->clientsBulkResetTrafficV3($emails);

            return [
                'ok' => $this->http->responseIsSuccess($res),
                'json' => $res,
                'message' => $this->panelJsonMsg($res),
            ];
        }
        $ok = 0;
        $fail = 0;
        foreach ($this->normalizeClientEmails($emails) as $em) {
            $r = $this->resetClientTraffic(0, $em);
            if ($this->http->responseIsSuccess($r)) {
                $ok++;
            } else {
                $fail++;
            }
        }

        return ['ok' => $fail === 0, 'succeeded' => $ok, 'failed' => $fail];
    }

    /** @return array<string, mixed>|null */
    public function clientsResetAllTrafficV3(): ?array
    {
        $r = $this->http->request('clients/resetAllTraffics', 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @param  array<int, string>  $emails */
    public function clientsLastOnlineV3(array $emails = []): ?array
    {
        $body = [];
        $list = $this->normalizeClientEmails($emails);
        if ($list !== []) {
            $body['emails'] = $list;
        }
        $r = $this->http->request('clients/lastOnline', 'POST', $body);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<string, mixed>|null */
    public function clientExternalLinksV3(string $email): ?array
    {
        $em = trim($email);
        if ($em === '') {
            return ['success' => false, 'msg' => 'email required'];
        }
        $r = $this->http->request('clients/'.rawurlencode($em).'/externalLinks', 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /** @return array<int, array<string, mixed>> */
    public function clientsForInboundId(int $inboundId): array
    {
        $iid = (int) $inboundId;
        if ($iid < 1) {
            return [];
        }
        if ($this->isV3ClientsApi()) {
            $out = [];
            $page = 1;
            while ($page <= 20) {
                $batch = $this->clientsListPagedV3($page, 500);
                if (! is_array($batch) || empty($batch['clients'])) {
                    break;
                }
                foreach ($batch['clients'] as $c) {
                    if (! is_array($c) || empty($c['email'])) {
                        continue;
                    }
                    $inboundIds = $c['inboundIds'] ?? $c['inbound_ids'] ?? [];
                    if (! is_array($inboundIds)) {
                        $inboundIds = [];
                    }
                    foreach ($inboundIds as $ciid) {
                        if ((int) $ciid === $iid) {
                            $out[] = $c;
                            break;
                        }
                    }
                }
                if (count($batch['clients']) < 500) {
                    break;
                }
                $page++;
            }

            return $out;
        }
        $inbound = $this->inboundGet($iid);
        if (! is_array($inbound)) {
            return [];
        }
        $settings = $inbound['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'])) {
            return [];
        }

        return $dec['clients'];
    }

    /** @return string|false */
    public function getConfigJson(): string|false
    {
        $c = $this->ctx->credentials();
        if (trim((string) ($c['panel_api_token'] ?? '')) !== '') {
            $r = $this->http->request('server/getConfigJson', 'GET');
            $out = $this->http->parseConfigJsonResponse($r);
            if ($out !== false) {
                return $out;
            }
        }
        if ($this->ctx->hasCookieCredentials() && $this->http->loginWithCookieSession(2, 200000)) {
            $r = $this->http->request('server/getConfigJson', 'GET', [], true);

            return $this->http->parseConfigJsonResponse($r);
        }

        return false;
    }

    /** @return array{ok:bool, step:string, url:string, http:int, bytes:int} */
    public function probeGetDb(): array
    {
        $url = $this->http->diagUrl('server/getDb', 'api');
        $c = $this->ctx->credentials();
        $hasToken = trim((string) ($c['panel_api_token'] ?? '')) !== '';
        $hasCookie = trim((string) ($c['panel_username'] ?? '')) !== ''
            && trim((string) ($c['panel_password'] ?? '')) !== '';
        if (! $hasToken && ! $hasCookie) {
            return [
                'ok' => false,
                'step' => 'missing_cookie_creds',
                'url' => $url,
                'http' => 0,
                'bytes' => 0,
            ];
        }
        $db = $this->getDbBinaryWithRetries(2, false);
        if ($db !== false && $db !== '') {
            $http = $this->lastGetDbHttp();

            return [
                'ok' => true,
                'step' => '',
                'url' => $url,
                'http' => $http > 0 ? $http : 200,
                'bytes' => strlen($db),
            ];
        }
        $step = $this->lastGetDbStep();
        if ($step === '') {
            $step = 'download';
        }
        $http = $this->lastGetDbHttp();
        if ($http === 404 && $step !== 'http_404') {
            $step = 'http_404';
        }

        return [
            'ok' => false,
            'step' => $step,
            'url' => $url,
            'http' => $http,
            'bytes' => 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    public function updateClient(string $clientId, array $payload): ?array
    {
        $r = $this->http->request('inbounds/updateClient/'.rawurlencode($clientId), 'POST', $payload);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
    }

    /**
     * @param  array<string, mixed>  $fullSettingsDec
     * @param  array<string, mixed>  $singleClient
     * @param  array<int, string>  $pathIdCandidates
     * @return array<string, mixed>|null
     */
    public function updateInboundClientSequential(
        int $inboundId,
        array $fullSettingsDec,
        array $singleClient,
        array $pathIdCandidates = [],
    ): ?array {
        $iid = (int) $inboundId;
        $targetEmail = trim((string) ($singleClient['email'] ?? ''));
        $singleWork = $singleClient;
        if ($this->isV3ClientsApi()) {
            if ($targetEmail !== '') {
                $singleWork['email'] = $targetEmail;
            }
            if ($targetEmail !== '' && ! empty($fullSettingsDec['clients']) && is_array($fullSettingsDec['clients'])) {
                foreach ($fullSettingsDec['clients'] as $cl) {
                    if (! is_array($cl) || ! isset($cl['email']) || (string) $cl['email'] !== $targetEmail) {
                        continue;
                    }
                    $singleWork = array_merge($cl, $singleWork);
                    $singleWork['email'] = $targetEmail;
                    break;
                }
            }
            $panelCl = $this->clientGetV3($targetEmail);
            if (is_array($panelCl)) {
                $singleWork = array_merge($panelCl, $singleWork);
                $singleWork['email'] = $targetEmail;
            }
            $scope = [];
            if (is_array($panelCl) && ! empty($panelCl['inboundIds']) && is_array($panelCl['inboundIds'])) {
                $scope = array_values(array_filter(array_map('intval', $panelCl['inboundIds'])));
            }
            if ($scope === [] && $iid > 0) {
                $scope = [$iid];
            }
            $bodyPatch = [];
            if (is_array($panelCl)) {
                foreach ($singleWork as $wk => $wv) {
                    if (! array_key_exists($wk, $panelCl) || $panelCl[$wk] !== $wv) {
                        $bodyPatch[$wk] = $wv;
                    }
                }
            } else {
                $bodyPatch = $singleWork;
            }

            return $this->clientUpdateV3($targetEmail, $bodyPatch, $scope);
        }
        $ids = [];
        if ($targetEmail !== '') {
            $singleWork['email'] = $targetEmail;
        }
        if ($targetEmail !== '' && ! empty($fullSettingsDec['clients']) && is_array($fullSettingsDec['clients'])) {
            foreach ($fullSettingsDec['clients'] as $cl) {
                if (! is_array($cl) || ! isset($cl['email']) || (string) $cl['email'] !== $targetEmail) {
                    continue;
                }
                $singleWork = array_merge($cl, $singleWork);
                $singleWork['email'] = $targetEmail;
                break;
            }
        }
        $inbound = $this->inboundGet($iid);
        $protocol = $this->normalizeInboundProtocol($inbound);
        $this->ensureClientProtocolFields($singleWork, $protocol);
        $pathPrimary = is_array($inbound)
            ? $this->resolveClientPathIdForUpdate('', $inbound, $targetEmail)
            : null;
        if (is_string($pathPrimary) && $pathPrimary !== '') {
            $ids[] = $pathPrimary;
        }
        foreach ($pathIdCandidates as $c) {
            $t = trim((string) $c);
            if ($t !== '' && ! in_array($t, $ids, true)) {
                $ids[] = $t;
            }
        }
        if ($ids === []) {
            return null;
        }
        $last = null;
        for ($attempt = 0; $attempt < 4; $attempt++) {
            if ($attempt > 0) {
                usleep(320000 + $attempt * 120000);
                $this->clearSession();
                $this->loginWithRetries(4, 280000);
                if ($targetEmail !== '' && $singleClient !== []) {
                    $fresh = $this->inboundGet($iid);
                    if (is_array($fresh)) {
                        $inbound = $fresh;
                        $protocol = $this->normalizeInboundProtocol($inbound);
                        $panelCl = $this->inboundClientByEmail($fresh, $targetEmail);
                        if (is_array($panelCl)) {
                            $singleWork = array_merge($panelCl, $singleClient);
                            $singleWork['email'] = $targetEmail;
                        }
                    }
                }
            }
            $this->ensureClientProtocolFields($singleWork, $protocol);
            $payloadDec = ['clients' => [$singleWork]];
            foreach ($ids as $pid) {
                $last = $this->updateClient($pid, [
                    'id' => $iid,
                    'settings' => json_encode($payloadDec, JSON_UNESCAPED_UNICODE),
                ]);
                if ($this->http->responseIsSuccess($last)) {
                    return $last;
                }
            }
        }

        return $last;
    }

    /**
     * Merge desired client fields into fresh inbound settings (preserves sibling clients).
     *
     * @param  array<string, mixed>|null  $inbound
     * @param  array<string, mixed>  $singleClient
     * @return array<string, mixed>|null
     */
    public function mergeClientIntoInboundSettings(?array $inbound, string $email, array $singleClient): ?array
    {
        if (! is_array($inbound)) {
            return null;
        }
        $settings = $inbound['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'])) {
            return null;
        }
        $want = trim($email);
        $matched = false;
        foreach ($dec['clients'] as &$cl) {
            if (! is_array($cl) || ! isset($cl['email']) || (string) $cl['email'] !== $want) {
                continue;
            }
            $cl = array_merge($cl, $singleClient);
            $cl['email'] = $want;
            $this->ensureClientPanelId($cl);
            $matched = true;
            break;
        }
        unset($cl);

        return $matched ? $dec : null;
    }

    /**
     * 3x-ui updateClient payload: settings with exactly one client in `clients`.
     *
     * @param  array<string, mixed>  $singleClient
     * @return array{clients: array<int, array<string, mixed>>}
     */
    public function buildUpdateClientSettingsPayload(array $singleClient): array
    {
        return ['clients' => [$singleClient]];
    }

    /** @param  array<string, mixed>|null  $inbound */
    public function normalizeInboundProtocol(?array $inbound): string
    {
        if (! is_array($inbound)) {
            return 'vless';
        }

        return strtolower(trim((string) ($inbound['protocol'] ?? 'vless')));
    }

    /** @param  array<string, mixed>  $client */
    public function ensureClientPanelId(array &$client): bool
    {
        if (! is_array($client)) {
            return false;
        }
        $parsed = $this->parseUuidValue($client['id'] ?? null);
        if (is_string($parsed)) {
            $client['id'] = $parsed;

            return true;
        }
        $cur = trim((string) ($client['id'] ?? ''));
        if ($this->isLikelyClientUuid($cur)) {
            $client['id'] = $cur;

            return true;
        }
        foreach (['password', 'subId'] as $k) {
            if (! array_key_exists($k, $client)) {
                continue;
            }
            $pv = $this->parseUuidValue($client[$k]);
            if (is_string($pv)) {
                $client['id'] = $pv;

                return true;
            }
            $t = trim((string) $client[$k]);
            if ($this->isLikelyClientUuid($t)) {
                $client['id'] = $t;

                return true;
            }
        }

        return false;
    }

    /** @param  array<string, mixed>  $client */
    public function ensureClientProtocolFields(array &$client, string $protocol): bool
    {
        if (! is_array($client)) {
            return false;
        }
        $protocol = strtolower(trim($protocol));
        $this->ensureClientPanelId($client);
        $uuid = trim((string) ($client['id'] ?? ''));
        if (! $this->isLikelyClientUuid($uuid)) {
            $uuid = '';
        }
        switch ($protocol) {
            case 'trojan':
                $pw = trim((string) ($client['password'] ?? ''));
                if ($pw === '' && $uuid !== '') {
                    $client['password'] = $uuid;
                    $pw = $uuid;
                }
                if ($pw === '') {
                    foreach (['password', 'subId'] as $k) {
                        $t = trim((string) ($client[$k] ?? ''));
                        if ($t !== '') {
                            $client['password'] = $t;
                            $pw = $t;
                            break;
                        }
                    }
                }

                return $pw !== '';
            case 'shadowsocks':
                return trim((string) ($client['email'] ?? '')) !== '';
            case 'hysteria':
            case 'hysteria2':
                $auth = trim((string) ($client['auth'] ?? ''));
                if ($auth === '' && $uuid !== '') {
                    $client['auth'] = $uuid;
                    $auth = $uuid;
                }

                return $auth !== '';
            default:
                if ($uuid !== '') {
                    $client['id'] = $uuid;

                    return true;
                }

                return $this->ensureClientPanelId($client);
        }
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @return string|null
     */
    public function resolveClientPathIdForUpdate(string $dbId, array $inbound, string $email): ?string
    {
        $protocol = $this->normalizeInboundProtocol($inbound);
        $sid = trim($dbId);
        $cl = $this->inboundClientByEmail($inbound, $email);
        if (is_array($cl)) {
            $row = $cl;
            $this->ensureClientProtocolFields($row, $protocol);
            $key = match ($protocol) {
                'trojan' => trim((string) ($row['password'] ?? '')),
                'shadowsocks' => trim((string) ($row['email'] ?? $email)),
                'hysteria', 'hysteria2' => trim((string) ($row['auth'] ?? '')),
                default => trim((string) ($row['id'] ?? '')),
            };
            if ($key !== '') {
                return $key;
            }
        }
        if ($this->isLikelyClientUuid($sid) && strcasecmp($sid, 'array') !== 0) {
            return $sid;
        }
        if ($protocol === 'shadowsocks') {
            $em = trim($email);

            return $em !== '' ? $em : null;
        }
        if ($protocol === 'trojan' && $sid !== '') {
            return $sid;
        }
        $em = trim($email);

        return $em !== '' ? $em : null;
    }

    /**
     * @param  array<string, mixed>  $inbound
     * @return string|null
     */
    public function resolveClientKeyForUpdate(string $dbId, array $inbound, string $email): ?string
    {
        $path = $this->resolveClientPathIdForUpdate($dbId, $inbound, $email);

        return is_string($path) && $path !== '' ? $path : null;
    }
}
