<?php

namespace App\Modules\Commerce\Services;

use App\Modules\Reseller\Services\ResellerScopeService;
use App\Modules\Reseller\Services\WholesalePricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP SimpleVPBot_Service_Admin_Catalog::apply_reseller_plan_rules parity.
 */
class PlanResellerRulesService
{
    public function __construct(
        protected ResellerScopeService $scope,
        protected WholesalePricingService $wholesale,
    ) {}

    /**
     * @param  array<string, mixed>  $rowData
     * @return array{row: array<string, mixed>, block?: bool, code?: string}
     */
    public function apply(int $actor, array $rowData, ?object $existing): array
    {
        if ($actor < 1) {
            return ['row' => $rowData];
        }

        $user = Schema::hasTable('svp_users')
            ? DB::table('svp_users')->where('id', $actor)->first()
            : null;
        if (! $user || (string) ($user->role ?? '') !== 'reseller') {
            return ['row' => $rowData, 'block' => true, 'code' => 'bad_actor'];
        }

        if ($existing !== null && (int) ($existing->owner_svp_user_id ?? 0) !== $actor) {
            return ['row' => $rowData, 'block' => true, 'code' => 'forbidden'];
        }

        $rowData = $this->mergeResellerPlanDefaults($actor, $rowData);

        $panelId = max(1, (int) ($rowData['panel_id'] ?? 1));
        $wholesaleLid = isset($rowData['wholesale_line_id']) ? (int) $rowData['wholesale_line_id'] : 0;

        if ($wholesaleLid < 1) {
            $auto = $this->autoPickWholesaleLineId($actor, $panelId);
            if ($auto > 0) {
                $rowData['wholesale_line_id'] = $auto;
                $wholesaleLid = $auto;
            }
        }

        if ($wholesaleLid < 1 && $this->assignedWholesaleLinesOnPanel($actor, $panelId) !== []) {
            return ['row' => $rowData, 'block' => true, 'code' => 'wholesale_line_required'];
        }

        $usingLine = false;
        if ($wholesaleLid > 0) {
            $lr = $this->wholesale->applyLineToPlanRow($actor, $rowData);
            if (empty($lr['ok'])) {
                return [
                    'row' => $rowData,
                    'block' => true,
                    'code' => (string) ($lr['code'] ?? 'wholesale_line_invalid'),
                ];
            }
            $usingLine = true;
        } else {
            $rowData['wholesale_line_id'] = null;
        }

        $panelId = max(1, (int) ($rowData['panel_id'] ?? 1));
        if (! $this->resellerMayUsePanelCatalog($actor, $panelId)) {
            return ['row' => $rowData, 'block' => true, 'code' => 'panel_not_allowed'];
        }

        $serviceType = strtolower((string) ($rowData['service_type'] ?? 'xray'));
        if ($serviceType === 'l2tp' && ! $this->resellerL2tpAllowedOnPanel($actor, $panelId)) {
            return ['row' => $rowData, 'block' => true, 'code' => 'l2tp_forbidden_for_reseller'];
        }

        $floor = $this->wholesale->wholesaleFloorUnit(
            $actor,
            $usingLine ? $wholesaleLid : 0,
            $panelId,
        );
        if ($floor > 0) {
            $ptype = (string) ($rowData['pricing_type'] ?? 'fixed');
            if ($ptype === 'per_gb') {
                $ppg = (float) ($rowData['price_per_gb'] ?? 0);
                if ($ppg + 0.000001 < $floor) {
                    return ['row' => $rowData, 'block' => true, 'code' => 'below_reseller_floor'];
                }
            } else {
                $gb = max(1, (int) ($rowData['traffic_gb'] ?? 0));
                $min = $floor * $gb;
                if ((float) ($rowData['price'] ?? 0) + 0.000001 < $min) {
                    return ['row' => $rowData, 'block' => true, 'code' => 'below_reseller_floor'];
                }
            }
        }

        if ($usingLine) {
            $rowData['owner_svp_user_id'] = $actor;

            return ['row' => $rowData];
        }

        if (! Schema::hasTable('svp_reseller_panel_prices')) {
            return ['row' => $rowData, 'block' => true, 'code' => 'module_missing'];
        }

        $ppRow = $this->panelPriceRow($actor, $panelId);
        if (! $this->rowAllowsPanelUse($ppRow)) {
            return ['row' => $rowData, 'block' => true, 'code' => 'panel_not_allowed'];
        }

        $dstype = isset($ppRow->default_service_type)
            ? strtolower((string) $ppRow->default_service_type)
            : 'xray';
        if (! in_array($dstype, ['xray', 'l2tp'], true)) {
            $dstype = 'xray';
        }
        $rowData['service_type'] = $dstype;
        if ($dstype === 'l2tp') {
            $rowData['inbound_id'] = 0;
            $l2 = max(0, (int) ($ppRow->default_l2tp_server_id ?? 0));
            $rowData['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
        } else {
            $rowData['inbound_id'] = max(0, (int) ($ppRow->default_inbound_id ?? 0));
            $rowData['l2tp_server_id'] = null;
        }
        $rowData['wholesale_line_id'] = null;
        $rowData['owner_svp_user_id'] = $actor;

        return ['row' => $rowData];
    }

    public function resellerOwnsPlan(int $actor, ?object $plan): bool
    {
        if ($actor < 1 || ! $plan) {
            return false;
        }

        return (int) ($plan->owner_svp_user_id ?? 0) === $actor;
    }

    public function resellerL2tpAllowedOnPanel(int $actor, int $panelId): bool
    {
        if ($actor < 1 || $panelId < 1) {
            return false;
        }

        $pp = $this->panelPriceRow($actor, $panelId);
        if ($pp && strtolower((string) ($pp->default_service_type ?? '')) === 'l2tp') {
            return true;
        }

        foreach ($this->assignedWholesaleLinesOnPanel($actor, $panelId) as $lineId) {
            $line = DB::table('svp_reseller_wholesale_lines')->where('id', $lineId)->first();
            if ($line && strtolower((string) ($line->default_service_type ?? '')) === 'l2tp') {
                return true;
            }
        }

        return false;
    }

    public function resellerMayUsePanelCatalog(int $actor, int $panelId): bool
    {
        if ($actor < 1 || $panelId < 1) {
            return false;
        }

        return in_array($panelId, $this->scope->allowedPanelIdsFor($actor), true);
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @return array<string, mixed>
     */
    public function mergeResellerPlanDefaults(int $actor, array $rowData): array
    {
        if ($actor < 1 || ! Schema::hasTable('svp_reseller_panel_prices')) {
            return $rowData;
        }

        $panelId = max(1, (int) ($rowData['panel_id'] ?? 1));
        $ppRow = $this->panelPriceRow($actor, $panelId);
        if (! $this->rowAllowsPanelUse($ppRow)) {
            // WP: still apply catalog defaults (wholesale line) when panel row disallows use.
            $catalog = $this->resolveCatalogDefaults($actor, $panelId);

            return $this->applyServiceTypeDefaults($rowData, $catalog);
        }

        $defaults = [
            'default_service_type' => (string) ($ppRow->default_service_type ?? 'xray'),
            'default_inbound_id' => max(0, (int) ($ppRow->default_inbound_id ?? 0)),
            'default_l2tp_server_id' => max(0, (int) ($ppRow->default_l2tp_server_id ?? 0)),
        ];

        return $this->applyServiceTypeDefaults($rowData, $defaults);
    }

    /**
     * @param  array<string, mixed>  $rowData
     * @param  array{default_service_type?:string, default_inbound_id?:int, default_l2tp_server_id?:int}  $defaults
     * @return array<string, mixed>
     */
    protected function applyServiceTypeDefaults(array $rowData, array $defaults): array
    {
        $dstype = strtolower((string) ($defaults['default_service_type'] ?? 'xray'));
        if (! in_array($dstype, ['xray', 'l2tp'], true)) {
            $dstype = 'xray';
        }
        $rowData['service_type'] = $dstype;
        if ($dstype === 'l2tp') {
            $rowData['inbound_id'] = 0;
            if (Schema::hasColumn('svp_plans', 'inbound_ids')) {
                $rowData['inbound_ids'] = null;
            }
            $l2 = max(0, (int) ($defaults['default_l2tp_server_id'] ?? 0));
            $rowData['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
        } else {
            $defInb = max(0, (int) ($defaults['default_inbound_id'] ?? 0));
            $rowData['inbound_id'] = $defInb;
            if (Schema::hasColumn('svp_plans', 'inbound_ids')) {
                $rowData['inbound_ids'] = $defInb > 0 ? json_encode([$defInb]) : null;
            }
            $rowData['l2tp_server_id'] = null;
        }

        return $rowData;
    }

    /**
     * WP SimpleVPBot_Model_Reseller_Panel_Price::resolve_catalog_defaults.
     *
     * @return array{price_per_gb:float, default_service_type:string, default_inbound_id:int, default_l2tp_server_id:int, wholesale_line_id:int, wholesale_line_label:string}
     */
    public function resolveCatalogDefaults(int $resellerSvpUserId, int $panelId): array
    {
        $out = [
            'price_per_gb' => 0.0,
            'default_service_type' => 'xray',
            'default_inbound_id' => 0,
            'default_l2tp_server_id' => 0,
            'wholesale_line_id' => 0,
            'wholesale_line_label' => '',
        ];
        if ($panelId < 1 || ! Schema::hasTable('svp_reseller_wholesale_lines')) {
            return $out;
        }

        $candidates = [];
        if ($resellerSvpUserId > 0 && Schema::hasTable('svp_reseller_wholesale_line_assignments')) {
            $assigned = DB::table('svp_reseller_wholesale_lines as l')
                ->join('svp_reseller_wholesale_line_assignments as a', 'a.line_id', '=', 'l.id')
                ->where('a.reseller_svp_user_id', $resellerSvpUserId)
                ->where('l.panel_id', $panelId)
                ->when(Schema::hasColumn('svp_reseller_wholesale_lines', 'active'), fn ($q) => $q->where('l.active', 1))
                ->orderBy('l.id')
                ->select('l.*')
                ->get();
            foreach ($assigned as $line) {
                $candidates[] = $line;
            }
        }
        if ($candidates === []) {
            $site = DB::table('svp_reseller_wholesale_lines')
                ->where('panel_id', $panelId)
                ->when(Schema::hasColumn('svp_reseller_wholesale_lines', 'active'), fn ($q) => $q->where('active', 1))
                ->orderBy('id')
                ->get();
            foreach ($site as $line) {
                $candidates[] = $line;
            }
        }
        if ($candidates === []) {
            return $out;
        }

        $line = $candidates[0];
        $lid = (int) ($line->id ?? 0);
        $ppb = (float) ($line->price_per_gb ?? 0);
        if (Schema::hasTable('svp_reseller_wholesale_tiers') && $lid > 0) {
            $tiers = DB::table('svp_reseller_wholesale_tiers')->where('line_id', $lid)->get();
            foreach ($tiers as $tier) {
                $tpp = (float) ($tier->price_per_gb ?? 0);
                if ($tpp > 0 && ($ppb <= 0 || $tpp < $ppb)) {
                    $ppb = $tpp;
                }
            }
        }
        $dstype = strtolower((string) ($line->default_service_type ?? 'xray'));
        if (! in_array($dstype, ['xray', 'l2tp'], true)) {
            $dstype = 'xray';
        }
        $out['price_per_gb'] = round($ppb, 4);
        $out['default_service_type'] = $dstype;
        $out['default_inbound_id'] = max(0, (int) ($line->default_inbound_id ?? $line->inbound_id ?? 0));
        $out['default_l2tp_server_id'] = max(0, (int) ($line->default_l2tp_server_id ?? 0));
        $out['wholesale_line_id'] = $lid;
        $out['wholesale_line_label'] = (string) ($line->label ?? '');

        return $out;
    }

    protected function autoPickWholesaleLineId(int $actor, int $panelId): int
    {
        $lineIds = $this->assignedWholesaleLinesOnPanel($actor, $panelId);
        if (count($lineIds) === 1) {
            return $lineIds[0];
        }

        return 0;
    }

    /** @return array<int, int> */
    protected function assignedWholesaleLinesOnPanel(int $actor, int $panelId): array
    {
        if ($actor < 1 || $panelId < 1
            || ! Schema::hasTable('svp_reseller_wholesale_line_assignments')
            || ! Schema::hasTable('svp_reseller_wholesale_lines')) {
            return [];
        }

        return DB::table('svp_reseller_wholesale_line_assignments as a')
            ->join('svp_reseller_wholesale_lines as l', 'l.id', '=', 'a.line_id')
            ->where('a.reseller_svp_user_id', $actor)
            ->where('l.panel_id', $panelId)
            ->when(Schema::hasColumn('svp_reseller_wholesale_lines', 'active'), fn ($q) => $q->where('l.active', 1))
            ->pluck('l.id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->unique()
            ->values()
            ->all();
    }

    protected function panelPriceRow(int $actor, int $panelId): ?object
    {
        if (! Schema::hasTable('svp_reseller_panel_prices')) {
            return null;
        }

        return DB::table('svp_reseller_panel_prices')
            ->where('reseller_svp_user_id', $actor)
            ->where('panel_id', $panelId)
            ->first();
    }

    protected function rowAllowsPanelUse(?object $row): bool
    {
        if (! $row) {
            return false;
        }
        $acc = (int) ($row->panel_access ?? 0);
        $price = (float) ($row->price_per_gb ?? 0);

        return $acc === 1 || $price > 0;
    }
}
