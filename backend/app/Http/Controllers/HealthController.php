<?php

namespace App\Http\Controllers;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function live(): JsonResponse
    {
        return response()->json(svp_ok());
    }

    public function ready(): JsonResponse
    {
        $checks = ['database' => false, 'cache' => false];

        try {
            DB::connection()->getPdo();
            $checks['database'] = true;
        } catch (\Throwable) {
            //
        }

        try {
            $store = config('cache.default');
            if ($store === 'redis') {
                Cache::store('redis')->get('svp_health_probe');
            }
            Cache::put('svp_health_probe', 1, 10);
            $checks['cache'] = true;
        } catch (\Throwable) {
            //
        }

        $ok = $checks['database'] && $checks['cache'];

        return response()->json(array_merge(svp_ok(['checks' => $checks]), ['ok' => $ok]), $ok ? 200 : 503);
    }

    public function deep(Request $request, XuiClient $xui): JsonResponse
    {
        if (! Schema::hasTable('svp_panels')) {
            return response()->json(svp_ok(['panel' => null, 'note' => 'no_panels_table']));
        }

        $panel = DB::table('svp_panels')->where('active', 1)->orderBy('sort_order')->first();
        if (! $panel) {
            return response()->json(svp_ok(['panel' => null, 'note' => 'no_active_panel']));
        }

        $result = $xui->testConnection((array) $panel);

        return response()->json(svp_ok([
            'panel_id' => (int) $panel->id,
            'probe' => $result,
        ]), ! empty($result['ok']) ? 200 : 502);
    }
}
