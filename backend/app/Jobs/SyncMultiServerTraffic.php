<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\MultiServerProvisioningService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize traffic usage across multi-server subscriptions
 * 
 * This job ensures that when a user consumes traffic on one server,
 * the same usage is reflected on all other servers in their subscription.
 * This prevents users from getting extra bandwidth by switching servers.
 */
class SyncMultiServerTraffic implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 300;

    /**
     * The subscription to sync (optional - if null, syncs all multi-server subscriptions)
     */
    private ?Subscription $subscription;

    /**
     * Create a new job instance.
     */
    public function __construct(?Subscription $subscription = null)
    {
        $this->subscription = $subscription;
    }

    /**
     * Execute the job.
     */
    public function handle(MultiServerProvisioningService $provisioningService): void
    {
        if ($this->subscription) {
            // Sync specific subscription
            $this->syncSubscription($this->subscription, $provisioningService);
        } else {
            // Sync all multi-server subscriptions
            $this->syncAllSubscriptions($provisioningService);
        }
    }

    /**
     * Sync all active multi-server subscriptions
     */
    private function syncAllSubscriptions(MultiServerProvisioningService $provisioningService): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereNull('server_id') // Multi-server subscriptions have null server_id
            ->where(function ($query) {
                $query->whereNull('expire_date')
                    ->orWhere('expire_date', '>', now());
            })
            ->cursor();

        $syncedCount = 0;
        $errorCount = 0;

        foreach ($subscriptions as $subscription) {
            try {
                $this->syncSubscription($subscription, $provisioningService);
                $syncedCount++;
            } catch (\Exception $e) {
                $errorCount++;
                Log::error("Failed to sync traffic for subscription {$subscription->id}: " . $e->getMessage());
            }
        }

        Log::info("SyncMultiServerTraffic completed: {$syncedCount} synced, {$errorCount} errors");
    }

    /**
     * Sync a single subscription
     */
    private function syncSubscription(Subscription $subscription, MultiServerProvisioningService $provisioningService): void
    {
        if (!$subscription->isMultiServer()) {
            return;
        }

        // Get stats from all servers
        $stats = $provisioningService->getAggregatedStats($subscription);
        
        if (empty($stats['servers'])) {
            return;
        }

        $maxUsedTraffic = $stats['max_used_traffic'];
        $currentUsedTraffic = $subscription->used_traffic;

        // Only sync if there's a significant difference (more than 1MB)
        if (abs($maxUsedTraffic - $currentUsedTraffic) > 1024 * 1024) {
            // Sync traffic across all servers
            $provisioningService->syncTrafficAcrossServers($subscription);
            
            Log::info("Synced traffic for subscription {$subscription->id}: {$currentUsedTraffic} -> {$maxUsedTraffic}");
        }

        // Check if subscription should be disabled (traffic or time expired)
        $this->checkExpiration($subscription, $maxUsedTraffic, $provisioningService);
    }

    /**
     * Check if subscription has expired and disable if necessary
     */
    private function checkExpiration(
        Subscription $subscription, 
        int $currentUsedTraffic,
        MultiServerProvisioningService $provisioningService
    ): void {
        $shouldDisable = false;
        $reason = '';

        // Check traffic limit
        if ($subscription->total_traffic > 0 && $currentUsedTraffic >= $subscription->total_traffic) {
            $shouldDisable = true;
            $reason = 'traffic_exceeded';
        }

        // Check expiration date
        if ($subscription->expire_date && $subscription->expire_date->isPast()) {
            $shouldDisable = true;
            $reason = 'time_expired';
        }

        if ($shouldDisable && $subscription->status === 'active') {
            $provisioningService->disableOnAllServers($subscription);
            $subscription->update(['status' => 'expired']);
            
            Log::info("Disabled subscription {$subscription->id} due to: {$reason}");
            
            // Dispatch notification job (if exists)
            // SendTelegramNotification::dispatch($subscription->user, "اشتراک شما به پایان رسید: {$reason}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SyncMultiServerTraffic job failed: ' . $exception->getMessage(), [
            'subscription_id' => $this->subscription?->id,
            'exception' => $exception,
        ]);
    }
}

