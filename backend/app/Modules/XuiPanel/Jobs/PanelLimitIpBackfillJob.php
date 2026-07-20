<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\PanelLimitIpBackfillService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PanelLimitIpBackfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PanelLimitIpBackfillService $backfill): void
    {
        if ($backfill->isDone()) {
            return;
        }

        CronTimer::run('svp:panel_limit_ip_backfill', fn () => $backfill->runBatch());
    }
}
