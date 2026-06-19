<?php

namespace App\Modules\XuiPanel\Http;

use App\Modules\XuiPanel\Services\InboundMapService;
use App\Modules\XuiPanel\Services\PanelAdminService;
use App\Modules\XuiPanel\Services\PanelMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
class PanelController
{
    public function __construct(
        protected PanelAdminService $panels,
        protected PanelMaintenanceService $maintenance,
        protected InboundMapService $inboundMap,
    ) {}

    public function inbounds(Request $request): JsonResponse
    {
        $result = $this->panels->inboundsList((int) $request->query('panel_id'));

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function inboundClients(Request $request): JsonResponse
    {
        $result = $this->panels->inboundClients(
            (int) $request->query('panel_id'),
            (int) $request->query('inbound_id'),
        );

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function inboundMapGet(Request $request): JsonResponse
    {
        $panelId = (int) $request->query('panel_id');
        if ($request->boolean('compare')) {
            return response()->json($this->inboundMap->compareContext($panelId));
        }
        $map = $this->inboundMap->getMap($panelId);

        return response()->json(svp_ok(['map' => $map, 'panel_id' => $panelId]));
    }

    public function inboundMapSave(Request $request): JsonResponse
    {
        $panelId = (int) $request->input('panel_id');
        $map = $request->input('map', []);
        if ($request->boolean('apply_to_db')) {
            return response()->json($this->inboundMap->applyToDb($panelId, is_array($map) ? $map : []));
        }
        $this->inboundMap->saveMap($panelId, is_array($map) ? $map : []);

        return response()->json(svp_ok(['panel_id' => $panelId]));
    }

    public function rebuildFromDb(Request $request): JsonResponse
    {
        $params = $request->all();
        $result = $this->maintenance->rebuildFromDb($params);

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function fix51200Traffic(Request $request): JsonResponse
    {
        $result = $this->maintenance->fix51200Traffic($request->all());

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
