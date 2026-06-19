<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Services\UserPortalStateBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserPortalController extends Controller
{
    public function __invoke(Request $request, UserPortalStateBuilder $builder): JsonResponse
    {
        $actor = $request->user();
        if (! $actor instanceof DashboardUser) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $svpUserId = (int) ($actor->svp_user_id ?? 0);
        $payload = $builder->build($svpUserId);
        $status = match ($payload['message'] ?? '') {
            'not_found', 'no_linked_user' => 404,
            default => ($payload['ok'] ?? false) ? 200 : 400,
        };

        return response()->json($payload, $status);
    }
}
