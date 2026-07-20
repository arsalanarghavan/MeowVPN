<?php

namespace App\Modules\XuiPanel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Repair local xui_client_id / sub_id from authoritative panel client rows (WP Service_Panel_Identity_Sync).
 */
class ServicePanelIdentitySyncService
{
    public const CRON_BATCH_SIZE = 50;

    public function __construct(protected XuiClient $xui) {}

    /**
     * @return array{ok:bool, repaired?:bool, reason?:string}
     */
    public function syncService(int $serviceId): array
    {
        if ($serviceId < 1 || ! Schema::hasTable('svp_services')) {
            return ['ok' => false, 'reason' => 'bad_params'];
        }

        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc) {
            return ['ok' => false, 'reason' => 'bad_service'];
        }
        if (strtolower((string) ($svc->service_type ?? 'xray')) === 'l2tp') {
            return ['ok' => false, 'reason' => 'bad_service'];
        }

        $em = trim((string) ($svc->email ?? ''));
        if ($em === '') {
            return ['ok' => false, 'reason' => 'missing_email'];
        }

        $pid = max(1, (int) ($svc->panel_id ?? 1));
        $iid = (int) ($svc->inbound_id ?? 0);

        $result = $this->xui->runWithPanel($pid, function () use ($svc, $serviceId, $em, $iid, $pid) {
            if (! $this->xui->loginWithRetries(4, 250000)) {
                return ['ok' => false, 'reason' => 'panel_login'];
            }
            $identity = $this->xui->fetchClientIdentityFromPanel($em, $iid);
            if (! is_array($identity)) {
                return ['ok' => false, 'reason' => 'panel_client_missing'];
            }

            $panelUuid = trim((string) ($identity['id'] ?? ''));
            $panelSub = trim((string) ($identity['subId'] ?? ''));
            $dbUuid = trim((string) ($svc->xui_client_uuid ?? $svc->xui_client_id ?? ''));
            $dbSub = trim((string) ($svc->sub_id ?? ''));
            $patch = [];

            if ($panelUuid !== '' && $this->xui->isLikelyClientUuid($panelUuid) && $panelUuid !== $dbUuid) {
                $patch['xui_client_id'] = $panelUuid;
                if (Schema::hasColumn('svp_services', 'xui_client_uuid')) {
                    $patch['xui_client_uuid'] = $panelUuid;
                }
            }
            if ($panelSub !== '' && $panelSub !== $dbSub) {
                $patch['sub_id'] = $panelSub;
            }
            if ($patch === []) {
                return ['ok' => true, 'repaired' => false];
            }

            DB::table('svp_services')->where('id', $serviceId)->update($patch);
            Log::info('identity_repaired', [
                'service_id' => $serviceId,
                'panel_id' => $pid,
                'email' => $em,
                'patch' => array_keys($patch),
            ]);

            return ['ok' => true, 'repaired' => true];
        });

        return is_array($result) ? $result : ['ok' => false, 'reason' => 'unknown'];
    }

    /**
     * @return array{ok:bool, scanned:int, repaired:int, reason?:string}
     */
    public function repairAllPanelIdentities(int $panelId = 0, int $limit = 500): array
    {
        $max = max(1, min(5000, $limit));
        $pidFilter = max(0, $panelId);
        if (! Schema::hasTable('svp_services')) {
            return ['ok' => false, 'reason' => 'no_model', 'scanned' => 0, 'repaired' => 0];
        }

        $scanned = 0;
        $repaired = 0;
        $q = DB::table('svp_services')->whereNull('deleted_at')->orderBy('id');
        foreach ($q->cursor() as $svc) {
            if ($scanned >= $max) {
                break;
            }
            if (strtolower((string) ($svc->service_type ?? 'xray')) === 'l2tp') {
                continue;
            }
            if (trim((string) ($svc->email ?? '')) === '') {
                continue;
            }
            $spid = max(1, (int) ($svc->panel_id ?? 1));
            if ($pidFilter > 0 && $spid !== $pidFilter) {
                continue;
            }
            ++$scanned;
            $res = $this->syncService((int) ($svc->id ?? 0));
            if (! empty($res['ok']) && ! empty($res['repaired'])) {
                ++$repaired;
            }
        }

        return [
            'ok' => true,
            'scanned' => $scanned,
            'repaired' => $repaired,
        ];
    }
}
