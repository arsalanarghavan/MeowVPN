<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\AdminState\AdminUserDetailBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends Controller
{
    public function show(Request $request, int $id, AdminUserDetailBuilder $builder): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof DashboardUser) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $payload = $builder->build(
            $id,
            $request,
            $actor->role === 'reseller',
            (int) ($actor->svp_user_id ?? 0),
        );

        $status = match ($payload['message'] ?? '') {
            'not_found' => 404,
            'forbidden' => 403,
            default => ($payload['ok'] ?? false) ? 200 : 400,
        };

        return response()->json($payload, $status);
    }

    public function search(Request $request, AdminUserDetailBuilder $builder, ResellerScopeService $scope): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof DashboardUser) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $moderatable = [];
        if ($actor->role === 'reseller') {
            $moderatable = $scope->moderatableUserIds((int) ($actor->svp_user_id ?? 0));
        } else {
            $ownerCtx = (int) $request->query('resellerContextId', 0);
            if ($ownerCtx > 0) {
                $validated = $scope->validateResellerContextId($ownerCtx);
                if ($validated === null) {
                    return response()->json(['ok' => false, 'message' => 'invalid_reseller_context'], 400);
                }
                $moderatable = $scope->moderatableUserIds($validated);
            }
        }

        return response()->json($builder->search(
            (string) $request->query('q', ''),
            $actor->role === 'reseller',
            (int) ($actor->svp_user_id ?? 0),
            $moderatable,
        ));
    }
}
