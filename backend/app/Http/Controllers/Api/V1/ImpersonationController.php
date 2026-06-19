<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Services\ImpersonationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function start(Request $request, ImpersonationService $impersonation): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof DashboardUser) {
            return response()->json(svp_err('forbidden'), 403);
        }

        if (app()->environment('production') && ! $request->secure()) {
            return response()->json(svp_err('https_required'), 403);
        }

        $targetId = (int) ($request->json('targetSvpUserId') ?? $request->input('target_svp_user_id', 0));
        $result = $impersonation->start($user, $targetId);

        if (empty($result['ok'])) {
            return response()->json($result, 400);
        }

        $impersonation->recordAudit('impersonation.start', $user, $targetId);

        return response()->json($result);
    }

    public function stop(Request $request, ImpersonationService $impersonation): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof DashboardUser) {
            return response()->json(svp_err('forbidden'), 403);
        }

        $tid = $impersonation->targetId();
        $result = $impersonation->stop($user);

        if (empty($result['ok'])) {
            return response()->json($result, 403);
        }

        if ($tid > 0) {
            $impersonation->recordAudit('impersonation.stop', $user, $tid);
        }

        return response()->json($result);
    }
}
