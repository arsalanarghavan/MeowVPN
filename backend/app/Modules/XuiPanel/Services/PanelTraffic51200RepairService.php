<?php

namespace App\Modules\XuiPanel\Services;

use App\Models\SvpPlan;
use App\Services\SettingsStore;
use App\Support\Xui\InboundTraffic;
use Illuminate\Support\Facades\DB;

class PanelTraffic51200RepairService
{
    private const BATCH_SIZE = 30;

    private const BYTES_PER_GB = 1073741824;

    public function __construct(
        protected XuiClient $xui,
        protected SettingsStore $settings,
    ) {}

    /** @param  array<string, mixed>  $opts */
    public function run(array $opts = []): array
    {
        $panelId = max(0, (int) ($opts['panel_id'] ?? 0));
        $dryRun = ! empty($opts['dry_run']);
        $offset = max(0, (int) ($opts['offset'] ?? 0));
        $limit = max(1, min(50, (int) ($opts['limit'] ?? self::BATCH_SIZE)));

        if ($panelId < 1) {
            return svp_err('invalid_panel');
        }

        $inboundMap = $this->inboundMap($panelId, $opts);
        $totals = ['fixed' => 0, 'skipped' => 0, 'failed' => 0, 'no_source' => 0];
        $errors = [];

        $rows = $this->fetchBatch($panelId, $offset, $limit);
        $total = $this->countRows($panelId);

        foreach ($rows as $svc) {
            $cur = (int) ($svc->total_traffic ?? 0);
            $isBug = InboundTraffic::is51200CapBugBytes($cur);
            $isWrong50 = InboundTraffic::isWrong50gbFallbackBytes($cur);
            if (! $isBug && ! $isWrong50) {
                $totals['skipped']++;

                continue;
            }

            $svcArr = $this->resolveInbound((array) $svc, $inboundMap);
            $email = trim((string) ($svcArr['email'] ?? ''));
            $iid = (int) ($svcArr['inbound_id'] ?? 0);
            if ($email === '' || $iid < 1) {
                $totals['failed']++;

                continue;
            }

            $fixed = $this->resolveQuotaBytes($svc, $panelId, $iid, $email, ! $dryRun);
            if ($fixed < 1) {
                $totals['no_source']++;

                continue;
            }
            if (! $isBug && abs($fixed - $cur) < (int) (self::BYTES_PER_GB / 2)) {
                $totals['skipped']++;

                continue;
            }

            if ($dryRun) {
                $totals['fixed']++;

                continue;
            }

            $fixedGb = max(1, (int) round($fixed / self::BYTES_PER_GB));
            $res = $this->xui->runWithPanel($panelId, function () use ($panelId, $iid, $email, $fixedGb, $svcArr) {
                if (! $this->xui->loginWithRetries()) {
                    return ['ok' => false, 'message' => 'login_fail'];
                }
                $patch = $this->xui->patchPanelClient($iid, $email, function (array &$cl) use ($fixedGb) {
                    $cl['totalGB'] = InboundTraffic::panelClientTotalgbJsonValue($fixedGb * self::BYTES_PER_GB);
                });
                if (empty($patch['ok'])) {
                    return $patch;
                }
                DB::table('svp_services')->where('id', (int) ($svcArr['id'] ?? 0))->update([
                    'total_traffic' => $fixedGb * self::BYTES_PER_GB,
                ]);

                return ['ok' => true];
            });

            if (! empty($res['ok'])) {
                $totals['fixed']++;
            } else {
                $totals['failed']++;
                if (count($errors) < 15) {
                    $errors[] = [
                        'service_id' => (int) ($svc->id ?? 0),
                        'email' => $email,
                        'reason' => (string) ($res['message'] ?? 'failed'),
                    ];
                }
            }
        }

        $nextOffset = $offset + count($rows);
        $done = $nextOffset >= $total || count($rows) < 1;

        return svp_ok([
            'dry_run' => $dryRun,
            'totals' => $totals,
            'errors' => $errors,
            'done' => $done,
            'next_offset' => $done ? $total : $nextOffset,
            'total' => $total,
            'processed' => count($rows),
        ]);
    }

    protected function resolveQuotaBytes(object $svc, int $panelId, int $iid, string $email, bool $fetchPanel): int
    {
        if ((int) ($svc->plan_id ?? 0) > 0) {
            $plan = SvpPlan::query()->find((int) $svc->plan_id);
            $planGb = (int) ($plan->traffic_gb ?? 0);
            if ($planGb > 0 && $planGb < 51200) {
                return $planGb * self::BYTES_PER_GB;
            }
        }
        if ($fetchPanel) {
            $fromPanel = $this->xui->runWithPanel($panelId, function () use ($iid, $email) {
                if (! $this->xui->loginWithRetries()) {
                    return 0;
                }
                $inbound = $this->xui->inboundGet($iid);
                $cl = $this->xui->inboundClientByEmail($inbound, $email);
                if (! is_array($cl)) {
                    return 0;
                }
                $raw = $cl['totalGB'] ?? $cl['total'] ?? 0;

                return InboundTraffic::totalgbToBytes($raw);
            });
            if (is_int($fromPanel) && $fromPanel > 0 && ! InboundTraffic::is51200CapBugBytes($fromPanel)) {
                return $fromPanel;
            }
        }

        return 0;
    }

    /** @param  array<string, mixed>  $opts */
    protected function inboundMap(int $panelId, array $opts): ?array
    {
        if (isset($opts['inbound_map']) && is_array($opts['inbound_map'])) {
            return $opts['inbound_map'];
        }
        $raw = $this->settings->get('panel_inbound_map_'.$panelId);
        if (is_string($raw)) {
            $dec = json_decode($raw, true);

            return is_array($dec) ? $dec : null;
        }

        return is_array($raw) ? $raw : null;
    }

    /** @param  array<string, mixed>  $svc */
    protected function resolveInbound(array $svc, ?array $map): array
    {
        if ($map === null || $map === []) {
            return $svc;
        }
        $dbIid = (int) ($svc['inbound_id'] ?? 0);
        if (isset($map[$dbIid]) && (int) $map[$dbIid] > 0) {
            $svc['inbound_id'] = (int) $map[$dbIid];
        }

        return $svc;
    }

    /** @return array<int, object> */
    protected function fetchBatch(int $panelId, int $offset, int $limit): array
    {
        return DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('panel_id', $panelId)
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', 'xray')->orWhere('service_type', '');
            })
            ->orderBy('id')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->all();
    }

    protected function countRows(int $panelId): int
    {
        return (int) DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('panel_id', $panelId)
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', 'xray')->orWhere('service_type', '');
            })
            ->count();
    }
}
