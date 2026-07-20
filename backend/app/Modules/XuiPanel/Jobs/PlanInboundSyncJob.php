<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\PlanInboundSyncService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PlanInboundSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $planId) {}

    public function handle(PlanInboundSyncService $sync): void
    {
        CronTimer::run('svp:plan_inbound_sync', fn () => $sync->processQueuedPlan($this->planId));
        if ($sync->hasQueuedJob($this->planId)) {
            self::dispatch($this->planId)->delay(now()->addSecond());
        }
    }
}
