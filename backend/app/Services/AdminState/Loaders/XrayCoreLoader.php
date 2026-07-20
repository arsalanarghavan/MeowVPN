<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminRowFormatter;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class XrayCoreLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        if (! svp_modules()->isEnabled('xray_core')) {
            return false;
        }

        return in_array($ctx->activeTab, [
            'vpn_server', 'xray_core', 'xray_inbounds', 'xray_hosts', 'tunnel_nodes', 'plans', 'configs', 'dashboard', 'monitoring',
        ], true);
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $nodes = [];
        $inbounds = [];
        $hosts = [];
        $tunnels = [];

        if (Schema::hasTable('svp_xray_nodes')) {
            $nodes = DB::table('svp_xray_nodes')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($r) => AdminRowFormatter::rowArray($r))->all();
        }
        if (Schema::hasTable('svp_xray_inbounds')) {
            $inbounds = DB::table('svp_xray_inbounds')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($r) => AdminRowFormatter::rowArray($r))->all();
        }
        if (Schema::hasTable('svp_xray_hosts')) {
            $hosts = DB::table('svp_xray_hosts')->orderByDesc('priority')->orderBy('id')->get()
                ->map(fn ($r) => AdminRowFormatter::rowArray($r))->all();
        }
        if (Schema::hasTable('svp_tunnel_endpoints')) {
            $tunnels = DB::table('svp_tunnel_endpoints')->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn ($r) => AdminRowFormatter::rowArray($r))->all();
        }

        $result->merge([
            'xrayNodes' => $nodes,
            'xrayInbounds' => $inbounds,
            'xrayHosts' => $hosts,
            'tunnelEndpoints' => $tunnels,
        ]);
    }
}
