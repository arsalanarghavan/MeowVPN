<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpMonitorHost;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\AdminState\MonitorHostSnapshotService;
use App\Services\AdminState\PanelHealthService;

class MonitoringLoader extends AbstractLoader
{
    public function __construct(
        protected PanelHealthService $panelHealth,
        protected MonitorHostSnapshotService $hostSnapshots,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsMonitoring();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $hosts = [];
        if ($this->tableExists('svp_monitor_hosts')) {
            $hosts = $this->fetchRows(
                SvpMonitorHost::query()->where('active', 1)->orderBy('sort_order')->orderBy('id')
            );
            $result->merge(['monitorHosts' => $hosts]);
        }

        if ($ctx->needsPanelHealth() || $ctx->needsLiveMetrics()) {
            $overview = is_array($result->data['overview'] ?? null) ? $result->data['overview'] : [];
            $panels = is_array($result->data['panels'] ?? null) ? $result->data['panels'] : [];
            $overview = $this->panelHealth->enrichOverview($overview, $panels, $ctx);
            if ($ctx->needsLiveMetrics() && $hosts !== []) {
                $overview['externalHostSnapshots'] = $this->hostSnapshots->snapshots($hosts, true);
            }
            $result->merge(['overview' => $overview]);
        }
    }
}
