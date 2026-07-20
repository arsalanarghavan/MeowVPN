<?php

namespace App\Modules\XuiPanel\Http;

use App\Modules\XuiPanel\Services\PanelOrphanClientsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PanelOrphanClientsController
{
    public function scan(Request $request, PanelOrphanClientsService $service): JsonResponse
    {
        $panelId = (int) $request->input('panel_id');
        $userId = (int) $request->input('user_id', 0);
        $serviceId = (int) $request->input('service_id', 0);
        $result = $service->scan($panelId, $userId, $serviceId);

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function delete(Request $request, PanelOrphanClientsService $service): JsonResponse
    {
        $panelId = (int) $request->input('panel_id');
        $emails = $request->input('emails', []);
        $confirm = ! empty($request->input('confirm'));
        $result = $service->deleteUnlinked($panelId, is_array($emails) ? $emails : [], $confirm);

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
