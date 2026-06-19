<?php

namespace App\Modules\Marketing\Jobs;

use App\Modules\Marketing\Services\MarketingAutomationService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarketingJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(MarketingAutomationService $automation): void
    {
        CronTimer::run('svp:marketing', fn () => $automation->runCron());
    }
}
