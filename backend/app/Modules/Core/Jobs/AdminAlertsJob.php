<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Services\AdminAlertsService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class AdminAlertsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(AdminAlertsService $alerts): void
    {
        CronTimer::run('svp:admin_alerts', fn () => $alerts->run());
    }
}
