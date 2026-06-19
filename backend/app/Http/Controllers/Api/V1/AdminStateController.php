<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminStateBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminStateController extends Controller
{
    public function __invoke(Request $request, AdminStateBuilder $builder): JsonResponse
    {
        return response()->json($builder->build($request->user(), $request));
    }
}
