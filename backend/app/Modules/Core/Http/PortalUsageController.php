<?php

namespace App\Modules\Core\Http;

use App\Http\Controllers\Controller;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Modules\Core\Services\Portal\UsageSampleService;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PortalUsageController extends Controller
{
    public function __invoke(Request $request, PortalLinkService $portal, UsageSampleService $samples): JsonResponse
    {
        $ctx = $portal->resolveFromRequest($request);
        if (empty($ctx['ok'])) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $serviceId = (int) $request->query('service_id', 0);
        if ($serviceId < 1) {
            $serviceId = (int) ($ctx['service_id'] ?? 0);
        }
        $range = (string) $request->query('range', '7d');
        if ($serviceId < 1) {
            $userId = (int) ($ctx['user_id'] ?? 0);
            if ($userId > 0) {
                $svc = DB::table('svp_services')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->orderByDesc('id')
                    ->first();
                if ($svc && ! L2tpProvisionerService::isL2tp($svc)) {
                    $serviceId = (int) $svc->id;
                }
            }
        }

        if ($serviceId < 1) {
            return response()->json(['points' => [], 'total_in_range' => 0]);
        }

        return response()->json($samples->chartForRange($serviceId, $range));
    }
}
