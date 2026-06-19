<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminQuery\LogsQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LogsController extends Controller
{
    public function index(Request $request, LogsQueryService $logs): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $per = max(1, min(100, (int) $request->query('per_page', 30)));
        $res = $logs->query($page, $per, (string) $request->query('level', ''), (string) $request->query('q', ''));

        return response()->json(svp_ok([
            'rows' => $res['rows'],
            'pagination' => ['page' => $page, 'perPage' => $per, 'total' => $res['total']],
        ]));
    }
}
