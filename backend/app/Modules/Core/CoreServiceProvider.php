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
        // Prefer class maps without constructing deps: avoids boot-time DI graphs.
        return array_merge(
            (new \ReflectionClass(UserMutations::class))->newInstanceWithoutConstructor()->handlers(),
            (new \ReflectionClass(CoreMutations::class))->newInstanceWithoutConstructor()->handlers(),
            (new \ReflectionClass(CommerceMutations::class))->newInstanceWithoutConstructor()->handlers(),
        );
    }

    protected function bootEnabled(): void
    {
        $this->loadRoutesFrom(__DIR__.'/routes.php');
    }
}
