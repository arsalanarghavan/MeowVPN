<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\CronStatusService;
use Illuminate\Http\JsonResponse;

class CronStatusController extends Controller
{
    public function __invoke(CronStatusService $status): JsonResponse
    {
        return response()->json($status->forDashboard());
    }
}
