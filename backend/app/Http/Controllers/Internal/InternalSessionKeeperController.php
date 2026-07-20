<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Modules\XuiPanel\Services\PanelSessionKeeperService;
use Illuminate\Http\JsonResponse;

class InternalSessionKeeperController extends Controller
{
    public function __invoke(PanelSessionKeeperService $keeper): JsonResponse
    {
        $result = $keeper->run();

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
