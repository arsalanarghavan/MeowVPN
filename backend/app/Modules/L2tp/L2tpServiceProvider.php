<?php

namespace App\Modules\L2tp;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\L2tp\Mutations\L2tpMutations;

class L2tpServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'l2tp';
    }

    public function mutationHandlers(): array
    {
        return app(L2tpMutations::class)->handlers();
    }
}
