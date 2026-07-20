<?php

namespace App\Modules\Core\Jobs;

use App\Services\LiveMetrics\LiveMetricsCollector;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class LiveMetricsCronJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(LiveMetricsCollector $collector): void
    {
        if (! svp_modules()->isEnabled('xui_panel')) {
            return;
        }
        $collector->maybeCollect(true);
    }
}
