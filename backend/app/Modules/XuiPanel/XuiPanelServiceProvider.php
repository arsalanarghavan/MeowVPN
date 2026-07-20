<?php

namespace App\Modules\XuiPanel;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\XuiPanel\Mutations\XuiPanelMutations;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Modules\XuiPanel\Services\XuiSessionStore;

class XuiPanelServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'xui_panel';
    }

    public function register(): void
    {
        $this->app->singleton(XuiSessionStore::class);
        $this->app->singleton(XuiClient::class);
    }

    public function mutationHandlers(): array
    {
        return (new \ReflectionClass(XuiPanelMutations::class))->newInstanceWithoutConstructor()->handlers();
    }
}
