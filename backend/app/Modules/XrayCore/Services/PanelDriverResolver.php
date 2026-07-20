<?php

namespace App\Modules\XrayCore\Services;

use App\Models\SvpPlan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelDriverResolver
{
    public function __construct(
        protected XuiPanelDriver $xui,
        protected NativeXrayDriver $native,
        protected LocalNodeService $localNode,
    ) {}

    public function forPlan(SvpPlan $plan): PanelDriverInterface
    {
        if ($this->planUsesNative($plan)) {
            return $this->native;
        }

        if (
            function_exists('svp_modules')
            && svp_modules()->isEnabled('pasarguard')
            && class_exists(\App\Modules\PasarGuard\Services\PasarGuardPanelContext::class)
            && \App\Modules\PasarGuard\Services\PasarGuardPanelContext::providerForId((int) ($plan->panel_id ?? 0)) === 'pasarguard'
        ) {
            return app(\App\Modules\PasarGuard\Services\PasarGuardPanelDriver::class);
        }

        return $this->xui;
    }

    /** @param  array<string, mixed>|object  $svc */
    public function forService(array|object $svc): PanelDriverInterface
    {
        $row = is_array($svc) ? $svc : (array) $svc;
        $driver = trim((string) ($row['panel_driver'] ?? ''));
        if ($driver === 'native' && svp_modules()->isEnabled('xray_core')) {
            return $this->native;
        }
        if (
            ($driver === 'pasarguard' || (
                function_exists('svp_modules')
                && svp_modules()->isEnabled('pasarguard')
                && class_exists(\App\Modules\PasarGuard\Services\PasarGuardPanelContext::class)
                && \App\Modules\PasarGuard\Services\PasarGuardPanelContext::providerForId((int) ($row['panel_id'] ?? 0)) === 'pasarguard'
            ))
            && class_exists(\App\Modules\PasarGuard\Services\PasarGuardPanelDriver::class)
        ) {
            return app(\App\Modules\PasarGuard\Services\PasarGuardPanelDriver::class);
        }

        return $this->xui;
    }

    public function planUsesNative(SvpPlan $plan): bool
    {
        if (! svp_modules()->isEnabled('xray_core')) {
            return false;
        }
        if ((string) ($plan->panel_driver ?? '') === 'native') {
            return true;
        }
        if (! Schema::hasColumn('svp_plans', 'xray_inbound_ref')) {
            return false;
        }
        $ref = (int) ($plan->xray_inbound_ref ?? 0);

        return $ref > 0;
    }

    public function resolveInboundRef(SvpPlan $plan): int
    {
        if (Schema::hasColumn('svp_plans', 'xray_inbound_ref')) {
            $ref = (int) ($plan->xray_inbound_ref ?? 0);
            if ($ref > 0) {
                return $ref;
            }
        }

        return max(0, (int) ($plan->inbound_id ?? 0));
    }

    public function resolveNodeId(SvpPlan $plan): int
    {
        if ($this->planUsesNative($plan)) {
            return max(1, $this->localNode->localNodeId());
        }

        $inboundRef = $this->resolveInboundRef($plan);
        if ($inboundRef > 0 && Schema::hasTable('svp_xray_inbounds')) {
            $nodeId = (int) DB::table('svp_xray_inbounds')->where('id', $inboundRef)->value('node_id');
            if ($nodeId > 0) {
                return $nodeId;
            }
        }

        return max(1, (int) ($plan->panel_id ?? 1));
    }
}
