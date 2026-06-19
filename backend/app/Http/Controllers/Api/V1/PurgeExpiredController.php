<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminQuery\PurgeExpiredQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurgeExpiredController extends Controller
{
    public function index(Request $request, PurgeExpiredQueryService $purge): JsonResponse
    {
        return response()->json($purge->list($request->query()));
    }
}
