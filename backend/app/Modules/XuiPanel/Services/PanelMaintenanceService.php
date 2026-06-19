<?php

namespace App\Modules\XuiPanel\Services;

class PanelMaintenanceService
{
    public function __construct(
        protected PanelRebuildService $rebuild,
        protected PanelTraffic51200RepairService $repair51200,
        protected XuiClient $xui,
    ) {}

    /** @param  array<string, mixed>  $args */
    public function rebuildFromDb(array $args): array
    {
        if (empty($args['confirm']) && empty($args['dry_run'])) {
            return svp_err('confirm_required');
        }

        return $this->rebuild->rebuildAll([
            'panel_id' => (int) ($args['panel_id'] ?? 0),
            'dry_run' => ! empty($args['dry_run']),
            'offset' => (int) ($args['offset'] ?? 0),
            'limit' => (int) ($args['limit'] ?? 40),
            'inbound_map' => $args['inbound_map'] ?? null,
        ]);
    }

    /** @param  array<string, mixed>  $args */
    public function fix51200Traffic(array $args): array
    {
        if (empty($args['confirm']) && empty($args['dry_run'])) {
            return svp_err('confirm_required');
        }

        return $this->repair51200->run([
            'panel_id' => (int) ($args['panel_id'] ?? 0),
            'dry_run' => ! empty($args['dry_run']),
            'offset' => (int) ($args['offset'] ?? 0),
            'limit' => (int) ($args['limit'] ?? 30),
            'inbound_map' => $args['inbound_map'] ?? null,
        ]);
    }

    public function inboundCatalog(int $panelId): array
    {
        if ($panelId < 1) {
            return svp_err('invalid_panel');
        }

        return $this->xui->runWithPanel($panelId, function () {
            if (! $this->xui->loginWithRetries()) {
                return svp_err('login_fail');
            }
            $raw = $this->xui->inboundsList();
            if (! is_array($raw)) {
                return svp_err('fetch_failed');
            }
            $list = [];
            foreach ($raw as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $list[] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'remark' => (string) ($row['remark'] ?? ''),
                    'protocol' => (string) ($row['protocol'] ?? ''),
                ];
            }

            return svp_ok(['inbounds' => $list]);
        });
    }
}
