<?php

namespace App\Modules\Tunnel;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Tunnel\Mutations\TunnelMutations;
use App\Modules\Tunnel\Services\TunnelDeployService;
use App\Modules\Tunnel\Services\TunnelEndpointService;

class TunnelServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'tunnel';
    }

    public function register(): void
    {
        $this->app->singleton(TunnelDeployService::class);
        $this->app->singleton(TunnelEndpointService::class);
    }

    public function mutationHandlers(): array
    {
        return app(TunnelMutations::class)->handlers();
    }
}
