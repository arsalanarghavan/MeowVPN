<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Services\AutorenewService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AutorenewJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AutorenewService $autorenew): void
    {
        CronTimer::run('svp:autorenew', fn () => $autorenew->run());
    }
}
