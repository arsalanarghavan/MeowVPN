<?php

namespace App\Services\AdminQuery;

use App\Modules\XuiPanel\Services\ConfigsSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InboundDisplayCatalogService
{
    public function __construct(protected ConfigsSyncService $sync) {}

    /** @return array<string, mixed> */
    public function catalog(int $panelId, bool $refresh = false): array
    {
        if ($panelId < 1) {
            return svp_err('invalid_panel');
        }

        if ($refresh) {
            $this->sync->syncPanelToDb($panelId, true);
        }

        $list = [];
        if (Schema::hasTable('svp_panel_inbound_api')) {
            $rows = DB::table('svp_panel_inbound_api')->where('panel_id', $panelId)->orderBy('inbound_id')->get();
            foreach ($rows as $row) {
                $raw = json_decode((string) ($row->inbound_json ?? ''), true);
                if (! is_array($raw)) {
                    continue;
                }
                $list[] = [
                    'id' => (int) ($raw['id'] ?? $row->inbound_id),
                    'remark' => (string) ($raw['remark'] ?? ''),
                    'protocol' => (string) ($raw['protocol'] ?? ''),
                    'port' => (int) ($raw['port'] ?? 0),
                ];
            }
        }
        if ($list === [] && Schema::hasTable('svp_panel_inbound_clients')) {
            $rows = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->selectRaw('inbound_id, MAX(inbound_remark) as remark, MAX(protocol) as protocol, MAX(port) as port')
                ->groupBy('inbound_id')
                ->orderBy('inbound_id')
                ->get();
            foreach ($rows as $row) {
                $list[] = [
                    'id' => (int) $row->inbound_id,
                    'remark' => (string) ($row->remark ?? ''),
                    'protocol' => (string) ($row->protocol ?? ''),
                    'port' => (int) ($row->port ?? 0),
                ];
            }
        }

        return svp_ok(['data' => ['inbounds' => $list]]);
    }
}
