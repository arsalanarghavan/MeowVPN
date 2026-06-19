<?php

namespace App\Modules\Bale;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Bale\Mutations\BaleMutations;

class BaleServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'bale';
    }

    public function mutationHandlers(): array
    {
        return app(BaleMutations::class)->handlers();
    }

    protected function bootEnabled(): void
    {
        //
    }
}
