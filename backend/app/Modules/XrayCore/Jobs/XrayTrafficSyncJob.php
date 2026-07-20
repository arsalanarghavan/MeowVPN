<?php

namespace App\Modules\XrayCore\Jobs;

use App\Modules\XrayCore\Services\TrafficSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class XrayTrafficSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TrafficSyncService $sync): void
    {
        if (! svp_modules()->isEnabled('xray_core')) {
            return;
        }
        $stats = $sync->syncAll();
        Log::channel('svp-panel')->info('xray.traffic_sync', $stats);
    }
}
