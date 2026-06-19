<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\PurgeExpiredService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PurgeExpiredJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PurgeExpiredService $purge): void
    {
        CronTimer::run('svp:purge_expired', fn () => $purge->runBatch(PurgeExpiredService::BATCH_LIMIT, 'cron', false));
    }
}
