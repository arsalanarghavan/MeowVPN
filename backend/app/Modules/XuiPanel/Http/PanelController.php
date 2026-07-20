<?php

namespace App\Modules\XuiPanel\Http;

use App\Modules\PasarGuard\Services\PasarGuardClient;
use App\Modules\PasarGuard\Services\PasarGuardPanelContext;
use App\Modules\XuiPanel\Services\InboundMapService;
use App\Modules\XuiPanel\Services\PanelAdminService;
use App\Modules\XuiPanel\Services\PanelMaintenanceService;
use App\Modules\XuiPanel\Services\ServicePanelOrphanClientsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PanelController
{
    public function __construct(
        protected PanelAdminService $panels,
        protected PanelMaintenanceService $maintenance,
        protected InboundMapService $inboundMap,
        protected ServicePanelOrphanClientsService $orphans,
    ) {}

    public function inbounds(Request $request): JsonResponse
    {
        $result = $this->panels->inboundsList((int) $request->query('panel_id'));

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    /**
     * PasarGuard user templates for plan picker (WP route_panel_templates parity).
     */
    public function templates(Request $request): JsonResponse
    {
        $panelId = (int) $request->query('panel_id');
        if ($panelId < 1) {
            return response()->json(svp_err('invalid_panel'), 400);
        }

        $panel = DB::table('svp_panels')->where('id', $panelId)->first();
        if (! $panel || ! PasarGuardPanelContext::isPasarGuard($panel)) {
            return response()->json(svp_ok([
                'data' => [
                    'templates' => [],
                    'provider' => 'xui',
                ],
            ]));
        }

        if (! function_exists('svp_modules') || ! svp_modules()->isEnabled('pasarguard') || ! class_exists(PasarGuardClient::class)) {
            return response()->json(svp_ok([
                'data' => [
                    'templates' => [],
                    'provider' => 'xui',
                ],
            ]));
        }

        /** @var PasarGuardClient $pg */
        $pg = app(PasarGuardClient::class);
        try {
            $list = $pg->runWithPanel($panelId, function () use ($pg) {
                if (! $pg->loginWithRetries(4, 200000)) {
                    return null;
                }

                return $pg->templatesListSimple();
            }, (array) $panel);
        } catch (\Throwable) {
            $list = null;
        }

        $ok = is_array($list);

        return response()->json([
            'ok' => $ok,
            'data' => [
                'templates' => $ok ? array_values($list) : [],
                'provider' => PasarGuardPanelContext::PROVIDER,
            ],
        ], $ok ? 200 : 400);
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

    public function orphanClientsScan(Request $request): JsonResponse
    {
        $result = $this->orphans->scan(
            (int) $request->input('panel_id'),
            (int) $request->input('user_id', 0),
            (int) $request->input('service_id', 0),
        );

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }

    public function orphanClientsDelete(Request $request): JsonResponse
    {
        $emails = $request->input('emails', []);
        $result = $this->orphans->deleteUnlinked(
            (int) $request->input('panel_id'),
            is_array($emails) ? $emails : [],
            $request->boolean('confirm'),
        );

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
