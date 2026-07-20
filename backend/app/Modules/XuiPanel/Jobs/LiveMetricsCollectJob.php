<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Services\LiveMetrics\LiveMetricsCollector;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LiveMetricsCollectJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(LiveMetricsCollector $collector): void
    {
        CronTimer::run('svp:live_metrics_collect', fn () => $collector->maybeCollect(true));
    }
}
