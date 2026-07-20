<?php

namespace App\Modules\Rial;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Rial\Mutations\RialMutations;
use App\Services\MutationRegistry;

class RialServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'rial';
    }

    public function mutationHandlers(): array
    {
        return app(RialMutations::class)->handlers();
    }

    public function boot(): void
    {
        $handlers = $this->mutationHandlers();
        if ($handlers !== []) {
            $this->app->make(MutationRegistry::class)->registerMany($handlers);
        }
        // Always register callback routes; controllers gate on module enablement.
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
