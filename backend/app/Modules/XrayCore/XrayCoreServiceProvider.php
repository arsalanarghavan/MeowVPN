<?php

namespace App\Modules\XrayCore;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\XrayCore\Mutations\XrayCoreMutations;
use App\Modules\XrayCore\Services\InboundAdminService;
use App\Modules\XrayCore\Services\LocalNodeService;
use App\Modules\XrayCore\Services\NativeXrayDriver;
use App\Modules\XrayCore\Services\NativeXrayProvisioner;
use App\Modules\XrayCore\Services\NodeAgentClient;
use App\Modules\XrayCore\Services\PanelDriverResolver;
use App\Modules\XrayCore\Services\SubscriptionUriBuilder;
use App\Modules\XrayCore\Services\TrafficSyncService;
use App\Modules\XrayCore\Services\XrayConfigBuilder;
use App\Modules\XrayCore\Services\XuiPanelDriver;

class XrayCoreServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'xray_core';
    }

    public function register(): void
    {
        $this->app->singleton(LocalNodeService::class);
        $this->app->singleton(XrayConfigBuilder::class);
        $this->app->singleton(NodeAgentClient::class);
        $this->app->singleton(NativeXrayProvisioner::class);
        $this->app->singleton(SubscriptionUriBuilder::class);
        $this->app->singleton(TrafficSyncService::class);
        $this->app->singleton(InboundAdminService::class);
        $this->app->singleton(XuiPanelDriver::class);
        $this->app->singleton(NativeXrayDriver::class);
        $this->app->singleton(PanelDriverResolver::class);
    }

    public function mutationHandlers(): array
    {
        return (new \ReflectionClass(XrayCoreMutations::class))->newInstanceWithoutConstructor()->handlers();
    }

    protected function bootEnabled(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }
        try {
            $this->app->make(LocalNodeService::class)->ensureLocalNode();
        } catch (\Throwable) {
            // pre-migrate
        }
    }
}
