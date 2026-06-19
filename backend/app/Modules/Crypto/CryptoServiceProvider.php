<?php

namespace App\Modules\Crypto;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Crypto\Mutations\CryptoMutations;
use App\Services\MutationRegistry;

class CryptoServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'crypto';
    }

    public function mutationHandlers(): array
    {
        return app(CryptoMutations::class)->handlers();
    }

    public function boot(): void
    {
        $handlers = $this->mutationHandlers();
        if ($handlers !== []) {
            $this->app->make(MutationRegistry::class)->registerMany($handlers);
        }
        if (svp_modules()->isEnabled($this->moduleKey())) {
            $this->loadRoutesFrom(__DIR__.'/routes.php');
        }
    }
}
