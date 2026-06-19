<?php

namespace App\Modules\Core\Jobs;

use App\Services\Bot\InboundQueueService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InboundQueueDrainJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(InboundQueueService $drain): void
    {
        CronTimer::run('svp:inbound_queue_drain', fn () => $drain->drainBatch());
    }
}
