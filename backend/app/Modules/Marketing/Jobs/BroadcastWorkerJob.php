<?php

namespace App\Modules\Marketing\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use App\Support\Metrics\CronTimer;
use Illuminate\Queue\SerializesModels;

class BroadcastWorkerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(\App\Modules\Marketing\Services\BroadcastWorkerService $worker): void
    {
        CronTimer::run('svp:broadcast', fn () => $worker->runBatch());
    }
}
