<?php

namespace App\Modules\Core\Jobs;

use App\Modules\Core\Services\ExpiryNotificationService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ExpiryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ExpiryNotificationService $expiry): void
    {
        CronTimer::run('svp:expiry', fn () => $expiry->run());
    }
}
