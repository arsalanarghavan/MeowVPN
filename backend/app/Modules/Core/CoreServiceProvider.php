<?php

namespace App\Modules\Core;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Commerce\Mutations\CommerceMutations;
use App\Modules\Core\Mutations\CoreMutations;
use App\Modules\Core\Mutations\UserMutations;

class CoreServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'core';
    }

    public function mutationHandlers(): array
    {
        return array_merge(
            app(UserMutations::class)->handlers(),
            app(CoreMutations::class)->handlers(),
            app(CommerceMutations::class)->handlers(),
        );
    }

    protected function bootEnabled(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
