<?php

namespace App\Modules\Tunnel\Jobs;

use App\Modules\Tunnel\Services\TunnelEndpointService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TunnelHealthJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(TunnelEndpointService $endpoints): void
    {
        if (! svp_modules()->isEnabled('tunnel')) {
            return;
        }
        $stats = $endpoints->healthCheckAll();
        Log::channel('svp-panel')->info('tunnel.health', $stats);
    }
}
