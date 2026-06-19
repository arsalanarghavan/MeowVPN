<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Services\DashboardBootBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    public function __invoke(Request $request, DashboardBootBuilder $bootBuilder): JsonResponse
    {
        $user = $request->user();
        if ($user instanceof DashboardUser) {
            return response()->json($bootBuilder->bootstrapApiPayload($user));
        }

        return response()->json($bootBuilder->publicBootstrapPayload($request));
    }
}
