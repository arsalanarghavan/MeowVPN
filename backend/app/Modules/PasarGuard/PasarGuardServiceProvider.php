<?php

namespace App\Modules\PasarGuard;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\PasarGuard\Services\PasarGuardClient;
use App\Modules\PasarGuard\Services\PasarGuardPanelDriver;
use App\Modules\PasarGuard\Services\PasarGuardSessionStore;

class PasarGuardServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'pasarguard';
    }

    public function register(): void
    {
        $this->app->singleton(PasarGuardSessionStore::class);
        $this->app->singleton(PasarGuardClient::class);
        $this->app->singleton(PasarGuardPanelDriver::class);
        $this->app->singleton(PanelClientFactory::class);
    }

    public function mutationHandlers(): array
    {
        return [
            'panel_xp' => [Mutations\PasarGuardMutations::class, 'panelXp'],
            'panel_test' => [Mutations\PasarGuardMutations::class, 'panelTest'],
        ];
    }
}
