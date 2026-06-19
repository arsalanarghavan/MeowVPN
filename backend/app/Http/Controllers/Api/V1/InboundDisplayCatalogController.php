<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\AdminQuery\InboundDisplayCatalogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InboundDisplayCatalogController extends Controller
{
    public function __invoke(
        Request $request,
        InboundDisplayCatalogService $catalog,
        ResellerScopeService $scope,
    ): JsonResponse {
        $panelId = (int) $request->query('panel_id');
        /** @var DashboardUser|null $user */
        $user = $request->user();
        if ($panelId < 1) {
            return response()->json(svp_err('invalid_panel'), 400);
        }
        if ($user?->role === 'reseller') {
            $actor = (int) ($user->svp_user_id ?? 0);
            if (! in_array($panelId, $scope->allowedPanelIdsFor($actor), true)) {
                return response()->json(svp_err('forbidden'), 403);
            }
        }

        $result = $catalog->catalog($panelId, $request->boolean('refresh'));

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
