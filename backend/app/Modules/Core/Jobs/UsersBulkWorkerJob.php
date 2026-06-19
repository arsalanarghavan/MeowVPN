<?php

namespace App\Modules\Core\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Modules\Core\Services\UsersBulkWorkerService;
use App\Support\Metrics\CronTimer;

class UsersBulkWorkerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(UsersBulkWorkerService $worker): void
    {
        CronTimer::run('svp:users_bulk', fn () => $worker->runBatch());
    }
}
