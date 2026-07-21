<?php

namespace App\Modules\Reseller\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WholesalePricingService
{
    public function validatePanelFloor(int $parentId, int $childId, int $panelId, float $price): array
    {
        if ($parentId < 1 || $childId < 1 || $panelId < 1) {
            return svp_ok();
        }

        if (! Schema::hasTable('svp_reseller_parent_panel_floors')) {
            return svp_ok();
        }

        $floor = DB::table('svp_reseller_parent_panel_floors')
            ->where('parent_svp_user_id', $parentId)
            ->where('child_svp_user_id', $childId)
            ->where('panel_id', $panelId)
            ->value('min_price_per_gb');

        if ($floor === null) {
            return svp_ok();
        }

        if ($price < (float) $floor) {
            return svp_err('price_below_floor', [
                'panel_id' => $panelId,
                'min_price_per_gb' => (float) $floor,
            ]);
        }

        return svp_ok();
    }

    /**
     * Apply wholesale line routing fields into plan row (reseller actor).
     *
     * @param  array<string, mixed>  $rowData
     * @return array{ok: bool, code?: string}
     */
    public function applyLineToPlanRow(int $actor, array &$rowData): array
    {
        $lid = isset($rowData['wholesale_line_id']) ? (int) $rowData['wholesale_line_id'] : 0;
        if ($actor < 1 || $lid < 1) {
            return ['ok' => true];
        }

        if (! Schema::hasTable('svp_reseller_wholesale_line_assignments')
            || ! Schema::hasTable('svp_reseller_wholesale_lines')) {
            return ['ok' => false, 'code' => 'wholesale_line_invalid'];
        }

        $assigned = DB::table('svp_reseller_wholesale_line_assignments')
            ->where('reseller_svp_user_id', $actor)
            ->where('line_id', $lid)
            ->exists();
        if (! $assigned) {
            return ['ok' => false, 'code' => 'wholesale_line_not_assigned'];
        }

        $line = DB::table('svp_reseller_wholesale_lines')->where('id', $lid)->first();
        if (! $line) {
            return ['ok' => false, 'code' => 'wholesale_line_invalid'];
        }
        if (Schema::hasColumn('svp_reseller_wholesale_lines', 'active') && ! (int) ($line->active ?? 0)) {
            return ['ok' => false, 'code' => 'wholesale_line_invalid'];
        }

        $rowData['panel_id'] = max(1, (int) ($line->panel_id ?? 1));
        $dstype = isset($line->default_service_type)
            ? strtolower((string) $line->default_service_type)
            : 'xray';
        if (! in_array($dstype, ['xray', 'l2tp'], true)) {
            $dstype = 'xray';
        }
        $rowData['service_type'] = $dstype;
        if ($dstype === 'l2tp') {
            $rowData['inbound_id'] = 0;
            $l2 = max(0, (int) ($line->default_l2tp_server_id ?? 0));
            $rowData['l2tp_server_id'] = $l2 > 0 ? $l2 : null;
        } else {
            $inbound = max(0, (int) ($line->default_inbound_id ?? $line->inbound_id ?? 0));
            $rowData['inbound_id'] = $inbound;
            $rowData['l2tp_server_id'] = null;
        }

        return ['ok' => true];
    }

    /** Effective min retail unit (per GB) from line + optional parent floor. */
    public function wholesaleFloorUnit(int $resellerSvpUserId, int $lineId, int $panelId = 1): float
    {
        $unit = 0.0;
        if ($lineId > 0) {
            $unit = $this->effectiveUnitPrice($resellerSvpUserId, $lineId);
        }
        if ($unit <= 0 && Schema::hasTable('svp_reseller_panel_prices')) {
            $unit = (float) (DB::table('svp_reseller_panel_prices')
                ->where('reseller_svp_user_id', $resellerSvpUserId)
                ->where('panel_id', $panelId)
                ->value('price_per_gb') ?? 0);
        }

        $parentFloor = 0.0;
        if ($resellerSvpUserId > 0
            && Schema::hasTable('svp_users')
            && Schema::hasTable('svp_reseller_parent_panel_floors')) {
            $parentId = (int) (DB::table('svp_users')->where('id', $resellerSvpUserId)->value('invited_by') ?? 0);
            if ($parentId > 0) {
                $parentFloor = (float) (DB::table('svp_reseller_parent_panel_floors')
                    ->where('parent_svp_user_id', $parentId)
                    ->where('child_svp_user_id', $resellerSvpUserId)
                    ->where('panel_id', $panelId)
                    ->value('min_price_per_gb') ?? 0);
            }
        }

        return max($unit, $parentFloor);
    }

    protected function effectiveUnitPrice(int $resellerSvpUserId, int $lineId): float
    {
        if ($lineId < 1 || ! Schema::hasTable('svp_reseller_wholesale_lines')) {
            return 0.0;
        }

        $line = DB::table('svp_reseller_wholesale_lines')->where('id', $lineId)->first();
        if (! $line) {
            return 0.0;
        }

        if (Schema::hasColumn('svp_reseller_wholesale_lines', 'price_per_gb')) {
            $direct = (float) ($line->price_per_gb ?? 0);
            if ($direct > 0) {
                return $direct;
            }
        }

        if (! Schema::hasTable('svp_reseller_wholesale_tiers')) {
            return 0.0;
        }

        $tier = DB::table('svp_reseller_wholesale_tiers')
            ->where('line_id', $lineId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();

        return $tier ? (float) ($tier->price_per_gb ?? 0) : 0.0;
    }

    /** @param  array<string, mixed>  $payload */
    public function saveLine(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $data = collect($payload)->only([
            'panel_id', 'inbound_id', 'label', 'price_per_gb', 'price_per_day', 'active',
        ])->filter(fn ($v) => $v !== null)->all();

        if ($id > 0) {
            DB::table('svp_reseller_wholesale_lines')->where('id', $id)->update($data);

            return svp_ok(['id' => $id]);
        }

        $newId = DB::table('svp_reseller_wholesale_lines')->insertGetId(array_merge($data, [
            'created_at' => now(),
        ]));

        return svp_ok(['id' => $newId]);
    }

    public function deleteLine(int $id): array
    {
        if ($id < 1) {
            return svp_err('invalid');
        }
        DB::table('svp_reseller_wholesale_lines')->where('id', $id)->delete();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function assignLines(array $payload): array
    {
        $resellerId = (int) ($payload['reseller_svp_user_id'] ?? $payload['svp_user_id'] ?? 0);
        $lineIds = (array) ($payload['line_ids'] ?? []);
        if ($resellerId < 1) {
            return svp_err('invalid');
        }

        DB::table('svp_reseller_wholesale_line_assignments')
            ->where('reseller_svp_user_id', $resellerId)
            ->delete();

        foreach ($lineIds as $lineId) {
            $lid = (int) $lineId;
            if ($lid < 1) {
                continue;
            }
            DB::table('svp_reseller_wholesale_line_assignments')->insert([
                'reseller_svp_user_id' => $resellerId,
                'line_id' => $lid,
                'created_at' => now(),
            ]);
        }

        return svp_ok(['reseller_svp_user_id' => $resellerId, 'count' => count($lineIds)]);
    }
}
