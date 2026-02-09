<?php

namespace App\Jobs;

use App\Services\ServiceLifecycleService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CleanupExpiredServices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @var string Run on central node; use queue:work --queue=sync,default on central. */
    public string $queue = 'sync';

    public function handle(ServiceLifecycleService $service): void
    {
        try {
            $service->cleanupExpiredSubscriptions();
            Log::info('Expired services cleanup completed successfully');
        } catch (\Exception $e) {
            Log::error('Expired services cleanup failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

