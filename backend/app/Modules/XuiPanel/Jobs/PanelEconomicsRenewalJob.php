<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\PanelEconomicsRenewalService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PanelEconomicsRenewalJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PanelEconomicsRenewalService $renewal): void
    {
        CronTimer::run('svp:panel_economics_renewal', fn () => $renewal->run());
    }
}
