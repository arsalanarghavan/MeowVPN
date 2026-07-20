<?php

namespace App\Modules\XuiPanel\Mutations;

use App\Models\SvpService;
use App\Modules\XuiPanel\Services\InboundAutolinkService;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Services\PanelMergeService;
use App\Modules\XuiPanel\Services\PanelOrphanClientsService;
use App\Modules\XuiPanel\Services\ServicePanelIdentitySyncService;
use App\Modules\XuiPanel\Services\ServicePanelTransferService;
use App\Services\Commerce\ServiceProvisioner;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\UnitEconomicsService;
use App\Models\DashboardUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class XuiPanelMutations
{
    public function __construct(
        protected XuiClient $xui,
        protected ConfigsSyncService $configs,
        protected UnitEconomicsService $economics,
        protected ServicePanelTransferService $panelTransfer,
        protected PanelMergeService $panelMerge,
        protected PanelOrphanClientsService $orphanClients,
        protected PanelClientFactory $panelFactory,
        protected ServicePanelIdentitySyncService $identitySync,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'panel_xp' => [self::class, 'panelXp'],
            'panel_test' => [self::class, 'panelTest'],
            'panel_merge_preview' => [self::class, 'panelMergePreview'],
            'panel_merge_execute' => [self::class, 'panelMergeExecute'],
            'configs_panel_del_orphans' => [self::class, 'configsPanelDelOrphans'],
            'panels_repair_identities' => [self::class, 'panelsRepairIdentities'],
            'configs_bulk_reset_traffic' => [self::class, 'configsBulkResetTraffic'],
            'configs_reset_all_panel_traffic' => [self::class, 'configsResetAllPanelTraffic'],
            'configs_panel_del_depleted' => [self::class, 'configsPanelDelDepleted'],
            'configs_client_fetch_ips' => [self::class, 'configsClientFetchIps'],
            'configs_client_clear_ips' => [self::class, 'configsClientClearIps'],
            'configs_client_set_inbounds' => [self::class, 'configsClientSetInbounds'],
            'configs_clients_bulk_set_inbounds' => [self::class, 'configsClientsBulkSetInbounds'],
            'configs_inbound_patch' => [self::class, 'configsInboundPatch'],
            'configs_delete_expired_older_than' => [self::class, 'configsDeleteExpiredOlderThan'],
            'service_panel_sync' => [self::class, 'servicePanelSync'],
            'service_panel_refresh' => [self::class, 'servicePanelRefresh'],
            'service_panel_delete_client' => [self::class, 'servicePanelDeleteClient'],
            'service_panel_transfer' => [self::class, 'servicePanelTransfer'],
            'service_apply_canonical_panel_identity' => [self::class, 'serviceApplyCanonicalPanelIdentity'],
            'service_regen_key' => [self::class, 'serviceRegenKey'],
            'service_regen_sub_id' => [self::class, 'serviceRegenSubId'],
            'service_set_limit_ip' => [self::class, 'serviceSetLimitIp'],
            'service_alerts_patch' => [self::class, 'serviceAlertsPatch'],
            'configs_panel_client_patch' => [self::class, 'configsPanelClientPatch'],
            'configs_clients_batch' => [self::class, 'configsClientsBatch'],
            'configs_assign_plan' => [self::class, 'configsAssignPlan'],
            'configs_client_toggle_enable' => [self::class, 'configsClientToggleEnable'],
            'configs_client_reset_traffic' => [self::class, 'configsClientResetTraffic'],
            'configs_client_delete' => [self::class, 'configsClientDelete'],
            'configs_delete_expired_linked' => [self::class, 'configsDeleteExpiredLinked'],
            'inbound_link' => [self::class, 'inboundLink'],
            'inbound_autolink' => [self::class, 'inboundAutolink'],
            'purge_expired_run_cron' => [self::class, 'purgeExpiredRunCron'],
            'purge_expired_purge_ready' => [self::class, 'purgeExpiredPurgeReady'],
            'purge_expired_purge_one' => [self::class, 'purgeExpiredPurgeOne'],
            'panel_economics_save' => [self::class, 'panelEconomicsSave'],
            'panel_economics_mark_paid' => [self::class, 'panelEconomicsMarkPaid'],
            'shared_economics_save' => [self::class, 'sharedEconomicsSave'],
            'unit_economics_save' => [self::class, 'unitEconomicsSave'],
            'unit_economics_config_save' => [self::class, 'unitEconomicsConfigSave'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function panelMergePreview(array $payload, ?Authenticatable $actor): array
    {
        $spid = (int) ($payload['source_panel_id'] ?? 0);
        $tpid = (int) ($payload['target_panel_id'] ?? 0);
        $ids = [];
        if (isset($payload['service_ids']) && is_array($payload['service_ids'])) {
            foreach ($payload['service_ids'] as $raw) {
                $n = (int) $raw;
                if ($n > 0) {
                    $ids[] = $n;
                }
            }
        }
        $r = $this->panelMerge->preview($spid, $tpid, $ids);
        if (empty($r['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($r['reason'] ?? 'preview_failed'),
                'data' => $r['data'] ?? null,
            ];
        }

        return ['ok' => true, 'data' => $r['data'] ?? []];
    }

    /** @param  array<string, mixed>  $payload */
    public function panelMergeExecute(array $payload, ?Authenticatable $actor): array
    {
        $spid = (int) ($payload['source_panel_id'] ?? 0);
        $tpid = (int) ($payload['target_panel_id'] ?? 0);
        $planMap = [];
        if (isset($payload['plan_map']) && is_array($payload['plan_map'])) {
            foreach ($payload['plan_map'] as $src => $tgt) {
                $srcId = (int) $src;
                $tgtId = (int) $tgt;
                if ($tgtId < 1) {
                    continue;
                }
                if ($srcId === 0) {
                    $planMap[0] = $tgtId;
                    continue;
                }
                if ($srcId > 0) {
                    $planMap[$srcId] = $tgtId;
                }
            }
        }
        $serviceIds = [];
        if (isset($payload['service_ids']) && is_array($payload['service_ids'])) {
            foreach ($payload['service_ids'] as $raw) {
                $n = (int) $raw;
                if ($n > 0) {
                    $serviceIds[] = $n;
                }
            }
        }
        $actorLabel = '';
        if ($actor && method_exists($actor, 'getAttribute')) {
            $actorLabel = (string) ($actor->getAttribute('email') ?? $actor->getAttribute('username') ?? '');
        }
        $opts = [
            'actor_label' => $actorLabel,
            'deactivate_source' => ! empty($payload['deactivate_source']),
            'delete_source_after' => ! empty($payload['delete_source_after']),
        ];
        if ($serviceIds !== []) {
            $opts['service_ids'] = $serviceIds;
        }
        $r = $this->panelMerge->execute($spid, $tpid, $planMap, $opts);
        $ok = ! empty($r['ok']) || (isset($r['message']) && (string) $r['message'] === 'partial_batch');

        return [
            'ok' => $ok,
            'message' => (string) ($r['message'] ?? ($ok ? 'ok' : 'failed')),
            'data' => $r['data'] ?? null,
            'reason' => $r['reason'] ?? null,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsPanelDelOrphans(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if ($this->panelFactory->isPasarGuardPanel($panelId)) {
            return ['ok' => false, 'message' => 'del_orphans_not_supported'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            if (! $client->isV3ClientsApi()) {
                return ['ok' => false, 'message' => 'v3_required'];
            }
            $res = $client->clientsDelOrphansV3();
            $ok = method_exists($client, 'responseIsSuccess')
                ? $client->responseIsSuccess($res)
                : (is_array($res) && (! empty($res['ok']) || ! empty($res['success']) || ! empty($res['obj'])));
            if (! $ok) {
                return ['ok' => false, 'message' => 'del_orphans_failed'];
            }
            $deleted = 0;
            if (is_array($res)) {
                $deleted = (int) ($res['obj']['deleted'] ?? $res['deleted'] ?? 0);
            }

            return ['ok' => true, 'data' => ['deleted' => $deleted]];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncPanelToDb($panelId, true);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function panelsRepairIdentities(array $payload, ?Authenticatable $actor): array
    {
        if (! $actor instanceof DashboardUser || $actor->role !== 'admin') {
            return ['ok' => false, 'message' => 'forbidden'];
        }
        $pid = isset($payload['panel_id']) ? max(0, (int) $payload['panel_id']) : 0;
        $limit = isset($payload['limit']) ? max(1, min(5000, (int) $payload['limit'])) : 500;
        $res = $this->identitySync->repairAllPanelIdentities($pid, $limit);
        if (empty($res['ok'])) {
            return [
                'ok' => false,
                'message' => (string) ($res['reason'] ?? 'repair_failed'),
            ];
        }

        return [
            'ok' => true,
            'scanned' => (int) ($res['scanned'] ?? 0),
            'repaired' => (int) ($res['repaired'] ?? 0),
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsBulkResetTraffic(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        $emails = $this->normalizeClientEmails($payload['emails'] ?? []);
        $emails = array_slice($emails, 0, 500);
        if ($emails === []) {
            return ['ok' => false, 'message' => 'empty_items'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($emails) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            $bulk = $client->resetClientsTrafficBulk($emails);
            if (empty($bulk['ok'])) {
                return [
                    'ok' => false,
                    'message' => 'bulk_reset_failed',
                    'data' => ['succeeded' => 0, 'failed' => count($emails)],
                ];
            }

            return [
                'ok' => true,
                'message' => 'ok',
                'data' => [
                    'succeeded' => (int) ($bulk['affected'] ?? $bulk['succeeded'] ?? $bulk['count'] ?? count($emails)),
                    'failed' => (int) ($bulk['failed'] ?? 0),
                ],
            ];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncPanelToDb($panelId, true);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsResetAllPanelTraffic(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            if (! $client->isV3ClientsApi()) {
                return ['ok' => false, 'message' => 'v3_required'];
            }
            $res = $client->clientsResetAllTrafficV3();
            if (! $this->clientResponseOk($client, $res)) {
                return ['ok' => false, 'message' => 'reset_all_failed'];
            }

            return ['ok' => true];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncPanelToDb($panelId, true);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsPanelDelDepleted(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if ($this->panelFactory->isPasarGuardPanel($panelId)) {
            return ['ok' => false, 'message' => 'del_depleted_not_supported'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            if (! $client->isV3ClientsApi()) {
                return ['ok' => false, 'message' => 'v3_required'];
            }
            $res = $client->clientsDelDepletedV3();
            if (! $this->clientResponseOk($client, $res)) {
                return ['ok' => false, 'message' => 'del_depleted_failed'];
            }
            $deleted = 0;
            if (is_array($res)) {
                $deleted = (int) ($res['obj']['deleted'] ?? $res['deleted'] ?? 0);
            }

            return ['ok' => true, 'data' => ['deleted' => $deleted]];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncPanelToDb($panelId, true);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientFetchIps(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        $email = trim((string) ($payload['email'] ?? ''));
        if ($panelId < 1 || $inboundId < 1 || $email === '') {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if ($this->panelFactory->isPasarGuardPanel($panelId)) {
            return [
                'ok' => true,
                'data' => [
                    'client_ips' => [],
                    'client_ips_json' => '[]',
                ],
            ];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($email) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            $json = $client->clientIps($email);
            $ips = method_exists($client, 'parseClientIpsResponse')
                ? $client->parseClientIpsResponse($json, 30)
                : [];

            return [
                'ok' => true,
                'data' => [
                    'client_ips' => $ips,
                    'client_ips_json' => json_encode($ips, JSON_UNESCAPED_UNICODE),
                ],
            ];
        });

        if (is_array($out) && ! empty($out['ok']) && isset($out['data']['client_ips_json']) && Schema::hasTable('svp_panel_inbound_clients')) {
            DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->where('inbound_id', $inboundId)
                ->where('email', $email)
                ->update(['client_ips_json' => (string) $out['data']['client_ips_json']]);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientClearIps(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        $email = trim((string) ($payload['email'] ?? ''));
        if ($panelId < 1 || $inboundId < 1 || $email === '') {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if ($this->panelFactory->isPasarGuardPanel($panelId)) {
            return ['ok' => true];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($email) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            $res = $client->clearClientIps($email);
            if (! $this->clientResponseOk($client, $res)) {
                return ['ok' => false, 'message' => 'clear_ips_failed'];
            }

            return ['ok' => true];
        });

        if (is_array($out) && ! empty($out['ok']) && Schema::hasTable('svp_panel_inbound_clients')) {
            DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->where('inbound_id', $inboundId)
                ->where('email', $email)
                ->update(['client_ips_json' => '[]']);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientSetInbounds(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        $email = trim((string) ($payload['email'] ?? ''));
        if ($panelId < 1 || $email === '') {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        $attach = $this->normalizeInboundIds($payload['attach_inbound_ids'] ?? []);
        $detach = $this->normalizeInboundIds($payload['detach_inbound_ids'] ?? []);
        if ($attach === [] && $detach === []) {
            return ['ok' => false, 'message' => 'no_inbound_changes'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($email, $attach, $detach) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            if (! $client->isV3ClientsApi()) {
                return ['ok' => false, 'message' => 'v3_required'];
            }
            if ($attach !== []) {
                $res = $client->clientAttachV3($email, $attach);
                if (! $this->clientResponseOk($client, $res)) {
                    return ['ok' => false, 'message' => 'attach_failed'];
                }
            }
            if ($detach !== []) {
                $res = $client->clientDetachV3($email, $detach);
                if (! $this->clientResponseOk($client, $res)) {
                    return ['ok' => false, 'message' => 'detach_failed'];
                }
            }

            return ['ok' => true];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncInboundsAfterMutation($panelId, array_values(array_unique(array_merge($attach, $detach))));
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientsBulkSetInbounds(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        $emails = array_slice($this->normalizeClientEmails($payload['emails'] ?? []), 0, 500);
        if ($emails === []) {
            return ['ok' => false, 'message' => 'empty_items'];
        }
        $attach = $this->normalizeInboundIds($payload['attach_inbound_ids'] ?? []);
        $detach = $this->normalizeInboundIds($payload['detach_inbound_ids'] ?? []);
        if ($attach === [] && $detach === []) {
            return ['ok' => false, 'message' => 'no_inbound_changes'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($emails, $attach, $detach) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            if (! $client->isV3ClientsApi()) {
                return ['ok' => false, 'message' => 'v3_required'];
            }
            if ($attach !== []) {
                if (method_exists($client, 'clientsBulkAttachV3')) {
                    $res = $client->clientsBulkAttachV3($emails, $attach);
                    if (! $this->clientResponseOk($client, $res)) {
                        return ['ok' => false, 'message' => 'bulk_attach_failed'];
                    }
                } else {
                    foreach ($emails as $em) {
                        $res = $client->clientAttachV3($em, $attach);
                        if (! $this->clientResponseOk($client, $res)) {
                            return ['ok' => false, 'message' => 'bulk_attach_failed'];
                        }
                    }
                }
            }
            if ($detach !== []) {
                if (method_exists($client, 'clientsBulkDetachV3')) {
                    $res = $client->clientsBulkDetachV3($emails, $detach);
                    if (! $this->clientResponseOk($client, $res)) {
                        return ['ok' => false, 'message' => 'bulk_detach_failed'];
                    }
                } else {
                    foreach ($emails as $em) {
                        $res = $client->clientDetachV3($em, $detach);
                        if (! $this->clientResponseOk($client, $res)) {
                            return ['ok' => false, 'message' => 'bulk_detach_failed'];
                        }
                    }
                }
            }

            return ['ok' => true];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncInboundsAfterMutation($panelId, array_values(array_unique(array_merge($attach, $detach))));
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsInboundPatch(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        if ($panelId < 1 || $inboundId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if ($this->panelFactory->isPasarGuardPanel($panelId)) {
            return ['ok' => false, 'message' => 'inbound_patch_not_supported'];
        }
        $patch = [];
        if (array_key_exists('remark', $payload)) {
            $patch['remark'] = (string) $payload['remark'];
        }
        if (array_key_exists('enable', $payload)) {
            $patch['enable'] = ! empty($payload['enable']);
        }
        if (array_key_exists('port', $payload)) {
            $patch['port'] = (int) $payload['port'];
        }
        if ($patch === []) {
            return ['ok' => false, 'message' => 'no_patch_fields'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($panelId, $inboundId, $patch) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'panel_login'];
            }
            if (! method_exists($client, 'inboundUpdate')) {
                return ['ok' => false, 'message' => 'inbound_patch_not_supported'];
            }
            $inbound = $client->inboundGet($inboundId);
            if (! is_array($inbound) || empty($inbound['id'])) {
                return ['ok' => false, 'message' => 'inbound_not_found'];
            }
            foreach ($patch as $k => $v) {
                $inbound[$k] = $v;
            }
            $res = $client->inboundUpdate($inbound);
            if (is_array($res) && (string) ($res['msg'] ?? '') === 'inbound_update_not_supported') {
                return ['ok' => false, 'message' => 'inbound_patch_not_supported'];
            }
            if (! $this->clientResponseOk($client, $res)) {
                return ['ok' => false, 'message' => 'inbound_patch_failed'];
            }
            if (Schema::hasTable('svp_panel_inbound_api')) {
                DB::table('svp_panel_inbound_api')->updateOrInsert(
                    ['panel_id' => $panelId, 'inbound_id' => $inboundId],
                    [
                        'inbound_json' => json_encode($inbound, JSON_UNESCAPED_UNICODE),
                        'synced_at' => now(),
                    ]
                );
            }

            return ['ok' => true];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncInboundsAfterMutation($panelId, [$inboundId]);
        }

        return is_array($out) ? $out : ['ok' => false, 'message' => 'panel_login'];
    }

    /** @param  array<string, mixed>  $payload */
    public function configsDeleteExpiredOlderThan(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        $minDays = max(0, min(3650, (int) ($payload['min_days'] ?? -1)));
        $expect = (int) ($payload['confirm_count'] ?? -1);
        $clients = $this->expiredOlderThanClients($panelId, $minDays, 50);
        $n = count($clients);
        if ($n < 1) {
            return ['ok' => false, 'message' => 'none'];
        }
        if ($expect !== $n) {
            return [
                'ok' => false,
                'message' => 'confirm_mismatch',
                'data' => ['expected_count' => $n],
            ];
        }

        $deleted = 0;
        $failed = [];
        foreach ($clients as $c) {
            $iid = (int) ($c['inbound_id'] ?? 0);
            $em = (string) ($c['email'] ?? '');
            $ls = (int) ($c['linked_service_id'] ?? 0);
            $r = $this->deletePanelClientByEmail($panelId, $iid, $em, $ls);
            if (empty($r['ok'])) {
                $failed[] = [
                    'inbound_id' => $iid,
                    'email' => $em,
                    'reason' => (string) ($r['reason'] ?? $r['message'] ?? 'failed'),
                ];
            } else {
                $deleted++;
            }
        }

        $ok = $failed === [];
        if ($ok) {
            $this->configs->syncPanelToDb($panelId, true);
        }

        return [
            'ok' => $ok,
            'message' => $ok ? 'ok' : 'partial',
            'data' => [
                'deleted' => $deleted,
                'failed' => $failed,
            ],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function panelXp(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = $this->encryptPanelSecrets(collect($payload)->only([
            'label', 'panel_url', 'panel_username', 'panel_password', 'panel_api_base',
            'panel_login_secret', 'panel_api_token', 'panel_api_flavor', 'subscription_public_base',
            'sort_order', 'active', 'panel_provider', 'panel_template_required',
        ])->filter(fn ($v) => $v !== null)->all());
        if (isset($data['panel_provider']) && $data['panel_provider'] === 'pasarguard') {
            $data['panel_api_flavor'] = 'pasarguard_v5';
            if (($data['panel_api_base'] ?? '') === '' || ($data['panel_api_base'] ?? '') === 'panel/api') {
                $data['panel_api_base'] = 'api';
            }
        }
        if (array_key_exists('panel_template_required', $data)) {
            $data['panel_template_required'] = ! empty($data['panel_template_required']) ? 1 : 0;
        }
        if ($id > 0) {
            DB::table('svp_panels')->where('id', $id)->update($data);

            return svp_ok(['panel_id' => $id]);
        }
        $newId = DB::table('svp_panels')->insertGetId(array_merge($data, ['created_at' => now()]));

        return svp_ok(['panel_id' => $newId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelTest(array $payload, ?Authenticatable $actor): array
    {
        $panel = DB::table('svp_panels')->where('id', (int) ($payload['panel_id'] ?? 0))->first();
        if (! $panel) {
            return svp_err('not_found');
        }
        if (
            function_exists('svp_modules')
            && svp_modules()->isEnabled('pasarguard')
            && class_exists(\App\Modules\PasarGuard\Services\PanelClientFactory::class)
        ) {
            $client = app(\App\Modules\PasarGuard\Services\PanelClientFactory::class)->forPanel($panel);

            return svp_ok($client->testConnection((array) $panel));
        }

        return svp_ok($this->xui->testConnection((array) $panel));
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelSync(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();
        if (! $panel) {
            return svp_err('panel_not_found');
        }

        $result = $this->xui->syncService((array) $panel, $serviceId);
        $this->configs->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);

        return svp_ok($result);
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelRefresh(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();
        if (! $panel) {
            return svp_err('panel_not_found');
        }

        return svp_ok($this->xui->refreshInbound((array) $panel, $serviceId));
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelDeleteClient(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $svc = SvpService::query()->find($serviceId);
        if (! $svc) {
            return svp_err('not_found');
        }
        $panel = DB::table('svp_panels')->where('id', (int) $svc->panel_id)->first();

        $result = $this->xui->deleteClient((array) ($panel ?? []), $serviceId);
        if ($svc) {
            $this->configs->syncInboundsAfterMutation((int) $svc->panel_id, [(int) $svc->inbound_id]);
        }

        return svp_ok($result);
    }

    /** @param  array<string, mixed>  $payload */
    public function servicePanelTransfer(array $payload, ?Authenticatable $actor): array
    {
        return $this->panelTransfer->transferFromPayload($payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceApplyCanonicalPanelIdentity(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return svp_err('not_found');
        }
        $user = DB::table('svp_users')->where('id', (int) $svc->user_id)->first();
        $canonical = \App\Support\Xui\ServiceNaming::provisionCanonicalLabel($user, null, 1);
        $email = \App\Support\Xui\ServiceNaming::uniquePanelClientId($canonical);
        DB::table('svp_services')->where('id', $serviceId)->update([
            'remark' => $canonical,
            'email' => $email,
            'display_label' => $canonical,
        ]);
        if ((int) $svc->panel_id > 0 && (int) $svc->inbound_id > 0 && trim((string) $svc->email) !== '') {
            $this->configs->patchClient([
                'service_id' => $serviceId,
                'client_remark' => $canonical,
                'client_email_new' => $email,
            ]);
        }

        return svp_ok(['service_id' => $serviceId, 'canonical' => $canonical, 'email' => $email]);
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceRegenKey(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        if (! SvpService::query()->find($serviceId)) {
            return svp_err('not_found');
        }

        return svp_ok($this->xui->regenerateKey($serviceId));
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceRegenSubId(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        if (! SvpService::query()->find($serviceId)) {
            return svp_err('not_found');
        }
        $r = $this->xui->regenerateSubId($serviceId);

        return svp_ok(['sub_id' => $r['sub_id'] ?? '']);
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceSetLimitIp(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        $limit = (int) ($payload['limit_ip'] ?? $payload['limit'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }

        return svp_ok($this->xui->setLimitIp($serviceId, $limit));
    }

    /** @param  array<string, mixed>  $payload */
    public function serviceAlertsPatch(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $alerts = $payload['alerts'] ?? $payload;
        DB::table('svp_services')->where('id', $serviceId)->update([
            'alerts_json' => json_encode(is_array($alerts) ? $alerts : []),
        ]);

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsPanelClientPatch(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->patchClient($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientsBatch(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->clientsBatch($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsAssignPlan(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->assignPlan($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientToggleEnable(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->toggleEnable($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientResetTraffic(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->resetTraffic($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsClientDelete(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->deleteClient($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function configsDeleteExpiredLinked(array $payload, ?Authenticatable $actor): array
    {
        return $this->configs->deleteExpiredLinked();
    }

    /** @param  array<string, mixed>  $payload */
    public function inboundLink(array $payload, ?Authenticatable $actor): array
    {
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($inboundId < 1 || $panelId < 1) {
            return svp_err('invalid');
        }
        if (Schema::hasTable('svp_inbounds')) {
            DB::table('svp_inbounds')->updateOrInsert(
                ['panel_id' => $panelId, 'inbound_id' => $inboundId],
                ['linked_at' => now()]
            );
        }

        return svp_ok(['inbound_id' => $inboundId, 'panel_id' => $panelId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function inboundAutolink(array $payload, ?Authenticatable $actor): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 1);
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        if ($inboundId < 1) {
            return svp_err('invalid');
        }

        $result = app(InboundAutolinkService::class)->run($panelId, $inboundId);

        return svp_ok($result);
    }

    /** @param  array<string, mixed>  $payload */
    public function purgeExpiredRunCron(array $payload, ?Authenticatable $actor): array
    {
        $stats = app(\App\Modules\XuiPanel\Services\PurgeExpiredService::class)
            ->runBatch(
                max(1, min(100, (int) ($payload['limit'] ?? \App\Modules\XuiPanel\Services\PurgeExpiredService::BATCH_LIMIT))),
                'manual',
                ! empty($payload['force'])
            );

        return svp_ok(['data' => $stats]);
    }

    /** @param  array<string, mixed>  $payload */
    public function purgeExpiredPurgeReady(array $payload, ?Authenticatable $actor): array
    {
        if (empty($payload['confirm'])) {
            return svp_err('confirm_required');
        }
        $limit = max(1, min(100, (int) ($payload['limit'] ?? 50)));
        $stats = app(\App\Modules\XuiPanel\Services\PurgeExpiredService::class)->purgeReadyBatch($limit);

        return svp_ok(['data' => $stats]);
    }

    /** @param  array<string, mixed>  $payload */
    public function purgeExpiredPurgeOne(array $payload, ?Authenticatable $actor): array
    {
        $serviceId = (int) ($payload['service_id'] ?? 0);
        if ($serviceId < 1) {
            return svp_err('invalid');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return svp_err('not_found');
        }
        if ((int) ($svc->inbound_id ?? 0) > 0 && trim((string) ($svc->email ?? '')) !== '') {
            $this->xui->deleteClient([], $serviceId);
        }
        DB::table('svp_services')->where('id', $serviceId)->update(['deleted_at' => now()]);

        return svp_ok(['service_id' => $serviceId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelEconomicsSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->savePanelEconomics($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function panelEconomicsMarkPaid(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->markPanelPaid($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function sharedEconomicsSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->saveSharedEconomics($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function unitEconomicsSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->saveUnitEconomics($payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function unitEconomicsConfigSave(array $payload, ?Authenticatable $actor): array
    {
        return $this->economics->saveUnitEconomicsConfig($payload);
    }

    /** @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function encryptPanelSecrets(array $data): array
    {
        $cipher = app(\App\Services\PanelSecretCipher::class);
        foreach (['panel_password', 'panel_login_secret', 'panel_api_token'] as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            $plain = trim((string) $data[$key]);
            if ($plain === '') {
                continue;
            }
            $data[$key] = $cipher->encrypt($plain);
        }

        return $data;
    }

    protected function clientResponseOk(object $client, mixed $res): bool
    {
        if (method_exists($client, 'responseIsSuccess')) {
            return (bool) $client->responseIsSuccess($res);
        }

        return is_array($res) && (! empty($res['ok']) || ! empty($res['success']) || ! empty($res['obj']));
    }

    /** @param  mixed  $raw
     * @return list<string>
     */
    protected function normalizeClientEmails(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $row) {
            $em = trim((string) $row);
            if ($em !== '') {
                $out[] = $em;
            }
        }

        return array_values(array_unique($out));
    }

    /** @param  mixed  $raw
     * @return list<int>
     */
    protected function normalizeInboundIds(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $x) {
            $n = (int) $x;
            if ($n > 0) {
                $out[] = $n;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * @return list<array{inbound_id:int, email:string, linked_service_id:int}>
     */
    protected function expiredOlderThanClients(int $panelId, int $minDays, int $limit = 50): array
    {
        if ($panelId < 1 || ! Schema::hasTable('svp_panel_inbound_clients')) {
            return [];
        }
        $lim = max(1, min(100, $limit));
        $nowMs = (int) (time() * 1000);
        $cutoffMs = $nowMs - ($minDays * 86400 * 1000);
        $out = [];
        $rows = DB::table('svp_panel_inbound_clients')->where('panel_id', $panelId)->orderBy('id')->get();
        foreach ($rows as $row) {
            $iid = (int) ($row->inbound_id ?? 0);
            $em = trim((string) ($row->email ?? ''));
            if ($iid < 1 || $em === '') {
                continue;
            }
            $expiry = (int) ($row->expiry_ms ?? 0);
            $svc = null;
            if (Schema::hasTable('svp_services')) {
                $svc = DB::table('svp_services')
                    ->where('panel_id', $panelId)
                    ->where('inbound_id', $iid)
                    ->where('email', $em)
                    ->whereNull('deleted_at')
                    ->first();
            }
            if ($svc && ! empty($svc->expires_at)) {
                $expiry = max($expiry, (int) (strtotime((string) $svc->expires_at) * 1000));
            }
            if ($expiry < 1 || $expiry >= $nowMs || $expiry > $cutoffMs) {
                continue;
            }
            $out[] = [
                'inbound_id' => $iid,
                'email' => $em,
                'linked_service_id' => $svc ? (int) $svc->id : 0,
            ];
            if (count($out) >= $lim) {
                break;
            }
        }

        return $out;
    }

    /** @return array{ok:bool, message?:string, reason?:string} */
    protected function deletePanelClientByEmail(int $panelId, int $inboundId, string $email, int $linkedServiceId): array
    {
        $em = trim($email);
        if ($panelId < 1 || $inboundId < 1 || $em === '') {
            return ['ok' => false, 'message' => 'bad_params'];
        }
        if ($linkedServiceId > 0) {
            $svc = DB::table('svp_services')->where('id', $linkedServiceId)->whereNull('deleted_at')->first();
            if (! $svc
                || (int) $svc->panel_id !== $panelId
                || (int) $svc->inbound_id !== $inboundId
                || (string) $svc->email !== $em
            ) {
                return ['ok' => false, 'message' => 'service_mismatch'];
            }
            $this->xui->deleteClient([], $linkedServiceId);
            $this->configs->syncInboundsAfterMutation($panelId, [$inboundId]);

            return ['ok' => true];
        }
        $linked = DB::table('svp_services')
            ->where('panel_id', $panelId)
            ->where('inbound_id', $inboundId)
            ->where('email', $em)
            ->whereNull('deleted_at')
            ->first();
        if ($linked) {
            return ['ok' => false, 'message' => 'still_linked'];
        }

        $out = $this->panelFactory->runWithPanel($panelId, function ($client) use ($inboundId, $em) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'reason' => 'panel_login'];
            }
            $uuid = '';
            if (Schema::hasTable('svp_panel_inbound_clients')) {
                $crow = DB::table('svp_panel_inbound_clients')
                    ->where('inbound_id', $inboundId)
                    ->where('email', $em)
                    ->first();
                $uuid = trim((string) ($crow->xui_client_id ?? ''));
            }
            $res = $client->delClient($inboundId, $uuid !== '' ? $uuid : $em, $em);
            if (! $this->clientResponseOk($client, $res)) {
                return ['ok' => false, 'reason' => 'del_failed'];
            }

            return ['ok' => true];
        });

        if (is_array($out) && ! empty($out['ok'])) {
            $this->configs->syncInboundsAfterMutation($panelId, [$inboundId]);

            return ['ok' => true];
        }

        return [
            'ok' => false,
            'reason' => (string) (is_array($out) ? ($out['reason'] ?? 'failed') : 'failed'),
            'message' => (string) (is_array($out) ? ($out['reason'] ?? $out['message'] ?? 'failed') : 'failed'),
        ];
    }
}
