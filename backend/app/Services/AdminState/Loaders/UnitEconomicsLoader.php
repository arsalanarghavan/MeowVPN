<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class UnitEconomicsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsUnitEconomics() && $ctx->isAdmin;
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $unitEconomics = null;
        $panelMap = [];

        if ($this->tableExists('svp_unit_economics_config')) {
            $global = DB::table('svp_unit_economics_config')->where('scope', 'global')->first();
            if ($global) {
                $unitEconomics = [
                    'inputs' => (array) $global,
                    'salesVolume' => ['total_gb' => 0],
                ];
            }
        }

        if ($this->tableExists('svp_panel_economics_lines')) {
            $lines = DB::table('svp_panel_economics_lines')->get();
            foreach ($lines as $line) {
                $pid = (int) ($line->panel_id ?? 0);
                if ($pid > 0) {
                    $panelMap[$pid] = (array) $line;
                }
            }
        }

        $result->merge([
            'unitEconomics' => $unitEconomics,
            'panelEconomicsMap' => $panelMap,
        ]);
    }
}
