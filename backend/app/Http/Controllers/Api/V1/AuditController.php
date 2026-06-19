<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AdminQuery\AuditQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditController extends Controller
{
    public function index(Request $request, AuditQueryService $audit): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        $per = max(1, min(100, (int) $request->query('per_page', 30)));
        $res = $audit->query(
            (string) $request->query('domain', ''),
            (string) $request->query('event_type', ''),
            (string) $request->query('q', ''),
            $page,
            $per,
        );

        return response()->json(svp_ok([
            'rows' => $res['rows'],
            'pagination' => ['page' => $page, 'perPage' => $per, 'total' => $res['total']],
        ]));
    }
}
