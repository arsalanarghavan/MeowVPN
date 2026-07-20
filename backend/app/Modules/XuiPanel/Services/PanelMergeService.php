<?php

namespace App\Modules\XuiPanel\Services;

use App\Models\SvpPlan;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\PasarGuard\Services\PasarGuardPanelContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelMergeService
{
    public const EXECUTE_BATCH_MAX = 20;

    public function __construct(
        protected ServicePanelTransferService $transfer,
    ) {}

    /**
     * @param  array<int, string>  $serviceIds
     * @return array{ok:bool, reason?:string, data?:array<string,mixed>}
     */
    public function preview(int $sourcePanelId, int $targetPanelId, array $serviceIds = []): array
    {
        if ($sourcePanelId < 1 || $targetPanelId < 1 || $sourcePanelId === $targetPanelId) {
            return ['ok' => false, 'reason' => 'bad_params'];
        }
        $source = DB::table('svp_panels')->where('id', $sourcePanelId)->first();
        $target = DB::table('svp_panels')->where('id', $targetPanelId)->first();
        if (! $source || ! $target) {
            return ['ok' => false, 'reason' => 'panel_not_found'];
        }
        if ($this->panelProvider($source) !== $this->panelProvider($target)) {
            return ['ok' => false, 'reason' => 'provider_mismatch'];
        }

        $mergeMode = $this->panelsShareUrl($source, $target) ? 'db_only' : 'full_transfer';
        $sourcePlans = $this->plansForPanel($sourcePanelId);
        $targetPlans = $this->plansForPanel($targetPanelId);
        $serviceCounts = $this->serviceCountsByPlan($sourcePanelId, $serviceIds);
        $inUse = $this->plansInUse($sourcePlans, $serviceCounts);
        $suggested = $this->suggestMaps($inUse['plans'], $targetPlans);

        return [
            'ok' => true,
            'data' => [
                'source_panel_id' => $sourcePanelId,
                'target_panel_id' => $targetPanelId,
                'source_panel_label' => (string) ($source->label ?? '#'.$sourcePanelId),
                'target_panel_label' => (string) ($target->label ?? '#'.$targetPanelId),
                'merge_mode' => $mergeMode,
                'source_plans' => $sourcePlans,
                'target_plans' => $targetPlans,
                'service_counts' => $serviceCounts,
                'plans_in_use' => $inUse['plans'],
                'required_plan_ids' => $inUse['required_plan_ids'],
                'suggested_plan_map' => $suggested['plan_map'],
                'total_services' => array_sum($serviceCounts),
            ],
        ];
    }

    /**
     * @param  array<int, int>  $planMap
     * @param  array<string, mixed>  $opts
     * @return array{ok:bool, message?:string, reason?:string, data?:array<string,mixed>}
     */
    public function execute(int $sourcePanelId, int $targetPanelId, array $planMap, array $opts = []): array
    {
        if ($sourcePanelId < 1 || $targetPanelId < 1 || $sourcePanelId === $targetPanelId) {
            return ['ok' => false, 'reason' => 'bad_params'];
        }
        $source = DB::table('svp_panels')->where('id', $sourcePanelId)->first();
        $target = DB::table('svp_panels')->where('id', $targetPanelId)->first();
        if (! $source || ! $target) {
            return ['ok' => false, 'reason' => 'panel_not_found'];
        }
        if ($this->panelProvider($source) !== $this->panelProvider($target)) {
            return ['ok' => false, 'reason' => 'provider_mismatch'];
        }

        $mergeMode = $this->panelsShareUrl($source, $target) ? 'db_only' : 'full_transfer';
        $actor = (string) ($opts['actor_label'] ?? '');
        $filterIds = [];
        if (! empty($opts['service_ids']) && is_array($opts['service_ids'])) {
            foreach ($opts['service_ids'] as $raw) {
                $n = (int) $raw;
                if ($n > 0) {
                    $filterIds[] = $n;
                }
            }
            $filterIds = array_values(array_unique($filterIds));
        }

        $normalizedMap = $this->normalizePlanMap($planMap, $targetPanelId);
        $queue = $this->servicesQueue($sourcePanelId, $filterIds);
        if ($queue === []) {
            return ['ok' => false, 'reason' => 'no_services'];
        }

        $missingPlans = [];
        foreach ($queue as $row) {
            $srcPlan = (int) ($row['plan_id'] ?? 0);
            if (! isset($normalizedMap[$srcPlan]) || (int) $normalizedMap[$srcPlan] < 1) {
                $missingPlans[$srcPlan] = $srcPlan;
            }
        }
        if ($missingPlans !== []) {
            return [
                'ok' => false,
                'reason' => 'unmapped_plans',
                'message' => 'unmapped_plans',
                'data' => ['unmapped_plan_ids' => array_values($missingPlans)],
            ];
        }

        $batch = array_slice($queue, 0, self::EXECUTE_BATCH_MAX);
        $okn = 0;
        $fail = [];
        foreach ($batch as $row) {
            $sid = (int) $row['id'];
            $srcPlan = (int) $row['plan_id'];
            $tpln = (int) $normalizedMap[$srcPlan];
            if ($mergeMode === 'db_only') {
                $r = $this->transfer->reassignServiceDbOnly($sid, $targetPanelId, $tpln, $actor);
            } else {
                $r = $this->transfer->transferOne($sid, $targetPanelId, $tpln, $actor);
            }
            if (! empty($r['ok'])) {
                $okn++;
            } else {
                $fail[] = [
                    'service_id' => $sid,
                    'reason' => (string) ($r['reason'] ?? $r['message'] ?? 'failed'),
                ];
            }
        }

        $remaining = max(0, count($queue) - count($batch));
        if ($remaining === 0 && $fail === [] && ! empty($opts['deactivate_source'])) {
            DB::table('svp_panels')->where('id', $sourcePanelId)->update(['active' => 0]);
            DB::table('svp_plans')->where('panel_id', $sourcePanelId)->update(['active' => 0]);
        }

        return [
            'ok' => $fail === [] && $remaining < 1,
            'message' => ($fail === [] && $remaining < 1) ? 'ok' : ($fail === [] ? 'partial_batch' : 'partial'),
            'data' => [
                'merge_mode' => $mergeMode,
                'succeeded' => $okn,
                'failed' => $fail,
                'remaining' => $remaining,
                'total_queue' => count($queue),
            ],
        ];
    }

    protected function panelProvider(object $panel): string
    {
        $p = strtolower(trim((string) ($panel->panel_provider ?? 'xui')));

        return $p === PasarGuardPanelContext::PROVIDER ? PasarGuardPanelContext::PROVIDER : 'xui';
    }

    protected function panelsShareUrl(object $a, object $b): bool
    {
        $ua = $this->normalizePanelUrl((string) ($a->panel_url ?? ''));
        $ub = $this->normalizePanelUrl((string) ($b->panel_url ?? ''));

        return $ua !== '' && $ua === $ub;
    }

    protected function normalizePanelUrl(string $url): string
    {
        return rtrim(strtolower(trim($url)), '/');
    }

    /** @return array<int, array<string, mixed>> */
    protected function plansForPanel(int $panelId): array
    {
        $rows = DB::table('svp_plans')
            ->where('panel_id', $panelId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'name', 'category', 'inbound_id', 'active']);
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'id' => (int) $row->id,
                'name' => (string) ($row->name ?? ''),
                'category' => (string) ($row->category ?? ''),
                'active' => (int) ($row->active ?? 0),
                'inbound_id' => (int) ($row->inbound_id ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $serviceIds
     * @return array<int, int>
     */
    protected function serviceCountsByPlan(int $panelId, array $serviceIds = []): array
    {
        $q = DB::table('svp_services')
            ->where('panel_id', $panelId)
            ->whereNull('deleted_at')
            ->where('inbound_id', '>', 0)
            ->where(function ($w) {
                $w->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            });
        if ($serviceIds !== []) {
            $q->whereIn('id', array_map('intval', $serviceIds));
        }
        $out = [];
        foreach ($q->selectRaw('plan_id, COUNT(*) as c')->groupBy('plan_id')->get() as $row) {
            $out[(int) $row->plan_id] = (int) $row->c;
        }

        return $out;
    }

    /**
     * @param  array<int, string>  $serviceIds
     * @return array<int, array{id:int, plan_id:int}>
     */
    protected function servicesQueue(int $panelId, array $serviceIds = []): array
    {
        $q = DB::table('svp_services')
            ->where('panel_id', $panelId)
            ->whereNull('deleted_at')
            ->where('inbound_id', '>', 0)
            ->where(function ($w) {
                $w->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            })
            ->orderBy('id');
        if ($serviceIds !== []) {
            $q->whereIn('id', array_map('intval', $serviceIds));
        }
        $out = [];
        foreach ($q->get(['id', 'plan_id']) as $row) {
            $out[] = ['id' => (int) $row->id, 'plan_id' => (int) ($row->plan_id ?? 0)];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sourcePlans
     * @param  array<int, int>  $serviceCounts
     * @return array{plans:array<int,array<string,mixed>>,required_plan_ids:array<int,int>}
     */
    protected function plansInUse(array $sourcePlans, array $serviceCounts): array
    {
        $byId = [];
        foreach ($sourcePlans as $plan) {
            $id = (int) ($plan['id'] ?? 0);
            if ($id > 0) {
                $byId[$id] = $plan;
            }
        }
        $out = [];
        $required = [];
        foreach ($serviceCounts as $pid => $count) {
            if ((int) $count < 1) {
                continue;
            }
            $required[] = (int) $pid;
            if (isset($byId[(int) $pid])) {
                $row = $byId[(int) $pid];
                $out[] = array_merge($row, ['orphan' => false]);

                continue;
            }
            $planRow = SvpPlan::query()->find((int) $pid);
            $out[] = [
                'id' => (int) $pid,
                'name' => $planRow ? (string) $planRow->name : ('Plan #'.$pid.' (orphan)'),
                'category' => $planRow ? (string) ($planRow->category ?? '') : '',
                'active' => $planRow ? (int) $planRow->active : 0,
                'inbound_id' => $planRow ? (int) ($planRow->inbound_id ?? 0) : 0,
                'orphan' => true,
            ];
        }

        return ['plans' => $out, 'required_plan_ids' => array_values(array_unique($required))];
    }

    /** @param  array<int, int>  $planMap */
    /** @return array<int, int> */
    protected function normalizePlanMap(array $planMap, int $targetPanelId): array
    {
        $out = [];
        foreach ($planMap as $src => $tgt) {
            $srcId = (int) $src;
            $tgtId = (int) $tgt;
            if ($tgtId < 1) {
                continue;
            }
            $plan = SvpPlan::query()->find($tgtId);
            if (! $plan || (int) ($plan->panel_id ?? 0) !== $targetPanelId) {
                continue;
            }
            $out[$srcId] = $tgtId;
        }

        return $out;
    }

    /**
     * @param  array<int, array<string,mixed>>  $sourcePlans
     * @param  array<int, array<string,mixed>>  $targetPlans
     * @return array{plan_map:array<int,int>}
     */
    protected function suggestMaps(array $sourcePlans, array $targetPlans): array
    {
        $targetByName = [];
        foreach ($targetPlans as $plan) {
            $key = $this->normKey((string) ($plan['name'] ?? ''));
            if ($key === '') {
                continue;
            }
            $targetByName[$key][] = $plan;
        }
        $planMap = [];
        foreach ($sourcePlans as $plan) {
            $srcId = (int) ($plan['id'] ?? 0);
            if ($srcId < 1) {
                continue;
            }
            $nameKey = $this->normKey((string) ($plan['name'] ?? ''));
            if ($nameKey === '' || empty($targetByName[$nameKey])) {
                continue;
            }
            $candidates = $targetByName[$nameKey];
            if (count($candidates) === 1) {
                $planMap[$srcId] = (int) ($candidates[0]['id'] ?? 0);

                continue;
            }
            $srcCat = (string) ($plan['category'] ?? '');
            foreach ($candidates as $cand) {
                if ((string) ($cand['category'] ?? '') === $srcCat) {
                    $planMap[$srcId] = (int) ($cand['id'] ?? 0);
                    break;
                }
            }
            if (empty($planMap[$srcId]) && ! empty($candidates[0]['id'])) {
                $planMap[$srcId] = (int) $candidates[0]['id'];
            }
        }

        return ['plan_map' => $planMap];
    }

    protected function normKey(string $value): string
    {
        return strtolower(trim($value));
    }
}
