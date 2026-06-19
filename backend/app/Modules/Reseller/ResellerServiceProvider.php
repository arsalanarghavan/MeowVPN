<?php

namespace App\Modules\Reseller;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Reseller\Mutations\ResellerMutations;

class ResellerServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'reseller';
    }

    public function mutationHandlers(): array
    {
        return app(ResellerMutations::class)->handlers();
    }
}
