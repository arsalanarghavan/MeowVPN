<?php

namespace App\Modules\XuiPanel\Services;

use App\Support\Xui\InboundTraffic;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConfigsSyncService
{
    private const LOCK_PREFIX = 'svp_cfgsync_lock_';

    private const DONE_PREFIX = 'svp_cfgsync_done_';

    private const CACHE_STALE_AFTER = 900;

    public function __construct(protected XuiClient $xui) {}

    /** @return array<string, mixed> */
    public function syncPanelToDb(int $panelId, bool $force = false): array
    {
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if (! DB::table('svp_panels')->where('id', $panelId)->exists()) {
            return ['ok' => false, 'message' => 'panel_not_found'];
        }
        if (! Schema::hasTable('svp_panel_inbound_clients')) {
            return ['ok' => false, 'message' => 'no_cache_models'];
        }
        $lockKey = self::LOCK_PREFIX.$panelId;
        if (Cache::has($lockKey) && ! $force) {
            return ['ok' => true, 'data' => ['skipped' => true, 'reason' => 'locked']];
        }
        if (! $force) {
            $last = (int) Cache::get(self::DONE_PREFIX.$panelId, 0);
            if ($last > 0 && (time() - $last) < 900) {
                return ['ok' => true, 'data' => ['skipped' => true, 'reason' => 'recent']];
            }
        }
        Cache::put($lockKey, time(), 600);
        $inner = $this->xui->runWithPanel($panelId, function () use ($panelId) {
            if (! $this->xui->loginWithRetries()) {
                return ['ok' => false, 'message' => 'login_fail'];
            }

            return $this->syncInboundsLoggedIn($panelId, []);
        });
        Cache::forget($lockKey);
        if (! empty($inner['ok'])) {
            Cache::put(self::DONE_PREFIX.$panelId, time(), 86400);
        }

        return is_array($inner) ? $inner : ['ok' => false, 'message' => 'unknown'];
    }

    /**
     * @param  array<int>  $onlyInboundIds
     * @return array<string, mixed>
     */
    public function syncInboundsLoggedIn(int $panelId, array $onlyInboundIds): array
    {
        if ($panelId < 1 || ! Schema::hasTable('svp_panel_inbound_clients')) {
            return ['ok' => false, 'message' => 'no_cache_models'];
        }
        $planRows = $this->xrayPlanRows($panelId);
        $allowed = $this->planInboundIds($planRows);
        if ($allowed === []) {
            return ['ok' => true, 'data' => ['synced_inbounds' => 0, 'rows' => 0, 'truncated' => false]];
        }
        $targets = $onlyInboundIds === []
            ? $allowed
            : array_values(array_intersect(array_map('intval', $onlyInboundIds), $allowed));
        if ($targets === []) {
            return ['ok' => true, 'data' => ['synced_inbounds' => 0, 'rows' => 0, 'truncated' => false]];
        }
        $onRaw = $this->xui->onlines();
        $onlineEmails = $this->xui->parseOnlinesResponse($onRaw);
        $onlineSet = array_fill_keys($onlineEmails, true);
        $v3ByInbound = [];
        if ($this->xui->isV3ClientsApi()) {
            $page = 1;
            while ($page <= 20) {
                $batch = $this->xui->clientsListPagedV3($page, 500);
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
                        $ciid = (int) $ciid;
                        if ($ciid < 1) {
                            continue;
                        }
                        $v3ByInbound[$ciid] ??= [];
                        $v3ByInbound[$ciid][] = $c;
                    }
                }
                if (count($batch['clients']) < 500) {
                    break;
                }
                $page++;
            }
        }
        $rowTotal = 0;
        $truncated = false;
        $constMax = 500;
        $now = now();
        foreach ($targets as $iid) {
            $inb = $this->xui->inboundGet($iid);
            if (! is_array($inb)) {
                DB::table('svp_panel_inbound_api')->where('panel_id', $panelId)->where('inbound_id', $iid)->delete();
                DB::table('svp_panel_inbound_clients')->where('panel_id', $panelId)->where('inbound_id', $iid)->delete();
                continue;
            }
            DB::table('svp_panel_inbound_api')->updateOrInsert(
                ['panel_id' => $panelId, 'inbound_id' => $iid],
                ['inbound_json' => json_encode($inb, JSON_UNESCAPED_UNICODE), 'synced_at' => $now]
            );
            $dbRows = [];
            if ($this->xui->isV3ClientsApi() && isset($v3ByInbound[$iid])) {
                foreach ($v3ByInbound[$iid] as $c) {
                    $dbRows[] = $this->clientRowFromApi($panelId, $iid, $inb, $c, $onlineSet, $now);
                }
            } else {
                $settings = $inb['settings'] ?? '';
                $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
                $clients = is_array($dec) && isset($dec['clients']) ? $dec['clients'] : [];
                $n = 0;
                foreach ($clients as $c) {
                    if (! is_array($c) || empty($c['email'])) {
                        continue;
                    }
                    $dbRows[] = $this->clientRowFromApi($panelId, $iid, $inb, $c, $onlineSet, $now);
                    $n++;
                    if ($n >= $constMax) {
                        $truncated = true;
                        break;
                    }
                }
            }
            $this->replaceInboundBatch($panelId, $iid, $dbRows);
            $rowTotal += count($dbRows);
        }

        return [
            'ok' => true,
            'data' => [
                'synced_inbounds' => count($targets),
                'rows' => $rowTotal,
                'truncated' => $truncated,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function snapshot(int $panelId): array
    {
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'invalid_panel'];
        }
        if (! DB::table('svp_panels')->where('id', $panelId)->exists()) {
            return ['ok' => false, 'message' => 'panel_not_found'];
        }
        if (! Schema::hasTable('svp_panel_inbound_clients')) {
            return ['ok' => false, 'message' => 'no_cache_models'];
        }
        $cnt = (int) DB::table('svp_panel_inbound_clients')->where('panel_id', $panelId)->count();
        if ($cnt < 1) {
            $sz = $this->syncPanelToDb($panelId, true);
            if (empty($sz['ok'])) {
                return is_array($sz) ? $sz : ['ok' => false, 'message' => 'sync_failed'];
            }
        }
        $maxAt = DB::table('svp_panel_inbound_clients')->where('panel_id', $panelId)->max('synced_at');
        $cacheTs = $maxAt ? strtotime((string) $maxAt) : 0;
        $stale = $cacheTs < 1 || (time() - $cacheTs) > self::CACHE_STALE_AFTER;
        $planRows = $this->xrayPlanRows($panelId);
        $dbRows = DB::table('svp_panel_inbound_clients')->where('panel_id', $panelId)->get();
        $byInbound = [];
        foreach ($dbRows as $row) {
            $iid = (int) $row->inbound_id;
            $byInbound[$iid] ??= [];
            $byInbound[$iid][] = $row;
        }
        $plansOut = [];
        foreach ($planRows as $prow) {
            $planArr = (array) $prow;
            $iid = (int) ($planArr['inbound_id'] ?? 0);
            if ($iid < 1) {
                continue;
            }
            $clients = [];
            foreach ($byInbound[$iid] ?? [] as $crow) {
                $clients[] = $this->formatSnapshotClient($crow, $panelId);
            }
            $planArr['clients'] = $clients;
            $plansOut[] = $planArr;
        }

        return [
            'ok' => true,
            'data' => [
                'panel_id' => $panelId,
                'plans' => $plansOut,
                'cache_stale' => $stale,
                'cache_synced_at' => $maxAt,
                'expired_linked_ids' => $this->expiredLinkedServiceIds($panelId, 50),
            ],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function patchClient(array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $svc) {
            return svp_err('not_found');
        }
        $panelId = (int) $svc->panel_id;
        $patch = collect($payload)->except(['op', 'service_id', 'panel_id'])->all();
        if ($patch !== []) {
            DB::table('svp_services')->where('id', $serviceId)->update($patch);
            $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        }
        $result = $this->xui->runWithPanel($panelId, function () use ($svc) {
            if (! $this->xui->loginWithRetries()) {
                return ['ok' => false, 'message' => 'login_fail'];
            }

            return $this->xui->syncServiceRowToPanel((array) $svc);
        });
        $this->syncInboundsAfterMutation($panelId, [(int) $svc->inbound_id]);

        return ! empty($result['ok']) ? svp_ok(['service_id' => $serviceId]) : svp_err('panel_patch_failed', $result);
    }

    /** @param  array<string, mixed>  $payload */
    public function clientsBatch(array $payload): array
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($payload['service_ids'] ?? []))));
        $emails = array_values(array_filter(array_map('strval', (array) ($payload['emails'] ?? []))));
        if ($emails !== []) {
            $panelId = (int) ($payload['panel_id'] ?? 0);
            $inboundId = (int) ($payload['inbound_id'] ?? 0);
            $q = DB::table('svp_services')->whereNull('deleted_at')->whereIn('email', $emails);
            if ($panelId > 0) {
                $q->where('panel_id', $panelId);
            }
            if ($inboundId > 0) {
                $q->where('inbound_id', $inboundId);
            }
            foreach ($q->pluck('id') as $resolvedId) {
                $ids[] = (int) $resolvedId;
            }
            $ids = array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
        }
        if ($ids === []) {
            return svp_err('no_services');
        }
        $action = (string) ($payload['action'] ?? 'sync');
        $processed = 0;
        $inboundIds = [];
        foreach ($ids as $id) {
            $sid = (int) $id;
            if ($sid < 1) {
                continue;
            }
            $svc = DB::table('svp_services')->where('id', $sid)->first();
            if (! $svc) {
                continue;
            }
            $panelId = (int) $svc->panel_id;
            $this->xui->runWithPanel($panelId, function () use ($svc, $action) {
                if (! $this->xui->loginWithRetries()) {
                    return;
                }
                match ($action) {
                    'toggle_enable' => $this->xui->patchPanelClient((int) $svc->inbound_id, (string) $svc->email, function (array &$cl) use ($svc) {
                        $cl['enable'] = (bool) ($svc->client_enabled ?? 1);
                    }),
                    'reset_traffic' => $this->xui->resetClientTraffic((int) $svc->inbound_id, (string) $svc->email),
                    'delete' => $this->xui->delClient((int) $svc->inbound_id, (string) ($svc->xui_client_uuid ?? ''), (string) $svc->email),
                    default => $this->xui->syncServiceRowToPanel((array) $svc),
                };
            });
            $inboundIds[(int) $svc->panel_id][] = (int) $svc->inbound_id;
            $processed++;
        }
        foreach ($inboundIds as $pid => $iids) {
            $this->syncInboundsAfterMutation((int) $pid, array_values(array_unique($iids)));
        }

        return svp_ok(['processed' => $processed, 'action' => $action]);
    }

    /** @param  array<string, mixed>  $payload */
    public function assignPlan(array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $planId = (int) ($payload['plan_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        DB::table('svp_services')->where('id', $serviceId)->update(['plan_id' => $planId ?: null]);

        return svp_ok(['service_id' => $serviceId, 'plan_id' => $planId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function toggleEnable(array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $enabled = (bool) ($payload['enabled'] ?? true);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        DB::table('svp_services')->where('id', $serviceId)->update(['client_enabled' => $enabled ? 1 : 0]);
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if ($svc) {
            $this->xui->runWithPanel((int) $svc->panel_id, function () use ($svc, $enabled) {
                if ($this->xui->loginWithRetries()) {
                    $this->xui->patchPanelClient((int) $svc->inbound_id, (string) $svc->email, function (array &$cl) use ($enabled) {
                        $cl['enable'] = $enabled;
                    }, ['force_enable' => $enabled]);
                }
            });
            $this->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);
        }

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function resetTraffic(array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        DB::table('svp_services')->where('id', $serviceId)->update(['used_traffic' => 0]);
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if ($svc) {
            $this->xui->runWithPanel((int) $svc->panel_id, function () use ($svc) {
                if ($this->xui->loginWithRetries()) {
                    $this->xui->resetClientTraffic((int) $svc->inbound_id, (string) $svc->email);
                }
            });
            $this->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);
        }

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function deleteClient(array $payload): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if ($svc) {
            $this->xui->deleteClient([], $serviceId);
            $this->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);
        } else {
            DB::table('svp_services')->where('id', $serviceId)->update(['deleted_at' => now()]);
        }

        return svp_ok(['service_id' => $serviceId]);
    }

    public function deleteExpiredLinked(): array
    {
        $count = 0;
        $rows = DB::table('svp_services')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->limit(50)
            ->get();
        foreach ($rows as $svc) {
            $this->xui->deleteClient([], (int) $svc->id);
            $count++;
        }

        return svp_ok(['deleted' => $count]);
    }

    /** @param  array<int>  $inboundIds */
    public function syncInboundsAfterMutation(int $panelId, array $inboundIds): void
    {
        if ($panelId < 1 || $inboundIds === []) {
            return;
        }
        $this->xui->runWithPanel($panelId, function () use ($panelId, $inboundIds) {
            if ($this->xui->loginWithRetries(2, 200000)) {
                $this->syncInboundsLoggedIn($panelId, $inboundIds);
            }
        });
    }

    /** @return array<int, object> */
    protected function xrayPlanRows(int $panelId): array
    {
        return DB::table('svp_plans')
            ->where('panel_id', $panelId)
            ->where('active', 1)
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', 'xray')->orWhere('service_type', '');
            })
            ->orderBy('sort_order')
            ->get()
            ->all();
    }

    /** @param  array<int, object>  $planRows */
    /** @return array<int> */
    protected function planInboundIds(array $planRows): array
    {
        $ids = [];
        foreach ($planRows as $row) {
            $iid = (int) ($row->inbound_id ?? 0);
            if ($iid > 0) {
                $ids[$iid] = $iid;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  array<string, mixed>  $inb
     * @param  array<string, mixed>  $c
     * @param  array<string, bool>  $onlineSet
     * @return array<string, mixed>
     */
    protected function clientRowFromApi(int $panelId, int $iid, array $inb, array $c, array $onlineSet, $now): array
    {
        $email = trim((string) ($c['email'] ?? ''));
        $limitBytes = InboundTraffic::totalgbToBytes($c['totalGB'] ?? 0);
        $ips = [];
        if ($email !== '') {
            $json = $this->xui->clientIps($email);
            $ips = $this->xui->parseClientIpsResponse($json, 100);
        }

        return [
            'panel_id' => $panelId,
            'inbound_id' => $iid,
            'inbound_remark' => (string) ($inb['remark'] ?? ''),
            'protocol' => (string) ($inb['protocol'] ?? ''),
            'port' => (int) ($inb['port'] ?? 0),
            'email' => $email,
            'xui_client_id' => (string) ($c['id'] ?? ''),
            'remark' => (string) ($c['remark'] ?? $c['comment'] ?? ''),
            'comment' => (string) ($c['comment'] ?? $c['remark'] ?? ''),
            'tg_id' => (string) ($c['tgId'] ?? ''),
            'sub_id' => (string) ($c['subId'] ?? ''),
            'enable' => ! empty($c['enable']) ? 1 : 0,
            'total_gb' => $limitBytes > 0 ? (int) round($limitBytes / 1073741824) : 0,
            'expiry_ms' => (int) ($c['expiryTime'] ?? 0),
            'used_bytes' => (int) (($c['up'] ?? 0) + ($c['down'] ?? 0)),
            'limit_bytes' => $limitBytes,
            'is_online' => isset($onlineSet[$email]) ? 1 : 0,
            'client_ips_json' => json_encode($ips, JSON_UNESCAPED_UNICODE),
            'client_json' => json_encode($c, JSON_UNESCAPED_UNICODE),
            'synced_at' => $now,
        ];
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    protected function replaceInboundBatch(int $panelId, int $inboundId, array $rows): void
    {
        DB::table('svp_panel_inbound_clients')
            ->where('panel_id', $panelId)
            ->where('inbound_id', $inboundId)
            ->delete();
        foreach ($rows as $row) {
            DB::table('svp_panel_inbound_clients')->insert($row);
        }
    }

    /** @return array<string, mixed> */
    protected function formatSnapshotClient(object $crow, int $panelId): array
    {
        $svc = DB::table('svp_services')
            ->where('panel_id', $panelId)
            ->where('inbound_id', (int) $crow->inbound_id)
            ->where('email', (string) $crow->email)
            ->whereNull('deleted_at')
            ->first();

        return [
            'email' => (string) $crow->email,
            'enable' => (bool) $crow->enable,
            'is_online' => (bool) $crow->is_online,
            'service_id' => $svc ? (int) $svc->id : 0,
            'user_id' => $svc ? (int) $svc->user_id : 0,
            'plan_id' => $svc ? (int) ($svc->plan_id ?? 0) : 0,
            'limit_bytes' => (int) ($crow->limit_bytes ?? 0),
            'used_bytes' => (int) ($crow->used_bytes ?? 0),
            'expiry_ms' => (int) ($crow->expiry_ms ?? 0),
            'remark' => (string) ($crow->remark ?? ''),
        ];
    }

    /** @return array<int> */
    protected function expiredLinkedServiceIds(int $panelId, int $limit): array
    {
        return DB::table('svp_services')
            ->where('panel_id', $panelId)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->whereNull('deleted_at')
            ->limit($limit)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /** @return array<string, mixed>|null */
    protected function panelForService(int $serviceId): ?array
    {
        $svc = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $svc) {
            return null;
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();

        return $panel ? (array) $panel : null;
    }
}
