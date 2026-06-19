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

    public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool
    {
        return $this->http->loginWithRetries($maxAttempts, $delayUs);
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
            $r = $this->http->request('clients/ips/'.rawurlencode($email), 'POST', []);

            return is_array($r['json'] ?? null) ? $r['json'] : null;
        }
        $r = $this->http->request('inbounds/clientIps/'.rawurlencode($email), 'POST', []);

        return is_array($r['json'] ?? null) ? $r['json'] : null;
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

        return array_slice(
            array_values(array_unique(array_filter(array_map('trim', array_map('strval', (array) $ips))))),
            0,
            $lim
        );
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
}
