<?php

namespace App\Modules\Relay;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Relay\Mutations\RelayMutations;
use App\Modules\Relay\Services\RelayAdminClient;
use App\Modules\Relay\Services\TelegramRelayService;

class RelayServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'relay';
    }

    public function register(): void
    {
        $this->app->singleton(RelayAdminClient::class);
        $this->app->singleton(TelegramRelayService::class);
    }

    public function mutationHandlers(): array
    {
        return app(RelayMutations::class)->handlers();
    }

    protected function bootEnabled(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
