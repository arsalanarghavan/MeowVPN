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
