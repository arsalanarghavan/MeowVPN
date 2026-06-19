<?php

namespace App\Modules\XuiPanel\Http;

use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\XuiPanel\Services\PanelAdminService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConfigsController
{
    public function __construct(
        protected ConfigsSyncService $configs,
        protected PanelAdminService $panels,
    ) {}

    public function snapshot(Request $request): JsonResponse
    {
        $panelId = (int) $request->query('panel_id');
        $result = $this->configs->snapshot($panelId);

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function portalPayload(Request $request): JsonResponse
    {
        $result = $this->panels->portalPayload(
            (int) $request->query('service_id'),
            (int) $request->query('panel_id'),
            (int) $request->query('inbound_id'),
            (string) $request->query('email', ''),
        );

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function sync(Request $request): JsonResponse
    {
        $panelId = (int) $request->input('panel_id');
        $force = (bool) $request->input('force', true);
        $result = $this->configs->syncPanelToDb($panelId, $force);

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
