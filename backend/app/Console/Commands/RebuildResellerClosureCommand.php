<?php

namespace App\Console\Commands;

use App\Modules\Reseller\Services\ResellerClosureService;
use Illuminate\Console\Command;

class RebuildResellerClosureCommand extends Command
{
    protected $signature = 'svp:rebuild-reseller-closure';

    protected $description = 'Rebuild svp_reseller_closure from svp_users hierarchy';

    public function handle(ResellerClosureService $closure): int
    {
        $closure->rebuildAll();
        $this->info('Reseller closure table rebuilt.');

        return self::SUCCESS;
    }
}
