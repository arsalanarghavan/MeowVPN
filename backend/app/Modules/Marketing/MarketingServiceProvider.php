<?php

namespace App\Modules\Marketing;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Marketing\Mutations\MarketingMutations;

class MarketingServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'marketing';
    }

    public function mutationHandlers(): array
    {
        return app(MarketingMutations::class)->handlers();
    }
}
