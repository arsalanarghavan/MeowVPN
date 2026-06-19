<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use App\Services\AdminQuery\UsersBulkQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsersBulkController extends Controller
{
    public function jobs(Request $request, UsersBulkQueryService $bulk): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $per = max(1, min(100, (int) $request->query('per_page', 20)));
        /** @var DashboardUser|null $user */
        $user = $request->user();

        return response()->json($bulk->jobs($page, $per, $user));
    }

    public function jobItems(Request $request, UsersBulkQueryService $bulk): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $per = max(1, min(100, (int) $request->query('per_page', 50)));
        $jobId = (int) $request->query('job_id', 0);
        /** @var DashboardUser|null $user */
        $user = $request->user();

        return response()->json($bulk->jobItems($jobId, $page, $per, $user));
    }
}
