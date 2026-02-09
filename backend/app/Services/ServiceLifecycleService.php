<?php

namespace App\Services;

use App\Models\Subscription;
use App\Services\VpnPanelFactory;
use App\Services\MultiServerProvisioningService;
use App\Jobs\SendTelegramNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ServiceLifecycleService
{
    /**
     * Cache TTL for warning flags (24 hours)
     */
    private const WARNING_CACHE_TTL = 86400;

    public function __construct(
        private VpnPanelFactory $panelFactory,
        private MultiServerProvisioningService $multiServerService
    ) {}

    /**
     * Monitor and update subscription traffic (single-server subscriptions only)
     */
    public function monitorSubscriptions(): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->whereNotNull('server_id')
            ->with(['user', 'server'])
            ->get();

        foreach ($subscriptions as $subscription) {
            if (!$subscription->server) {
                continue;
            }
            try {
                $panelService = $this->panelFactory->make($subscription->server);
                $identifier = $subscription->server->isHiddify()
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                $stats = $panelService->getUserStats($subscription->server, $identifier);

                if ($stats) {
                    $subscription->update([
                        'used_traffic' => $stats['used_traffic'] ?? 0,
                    ]);

                    // Check for warnings
                    $this->checkWarnings($subscription);
                }
            } catch (\Exception $e) {
                Log::error("Failed to monitor subscription {$subscription->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Check and send warnings (with duplicate prevention)
     */
    private function checkWarnings(Subscription $subscription): void
    {
        // Skip if user has no telegram_id
        if (!$subscription->user || !$subscription->user->telegram_id) {
            return;
        }

        $telegramId = $subscription->user->telegram_id;

        // Traffic warning (20% remaining)
        if ($subscription->total_traffic > 0) {
            $remaining = $subscription->getRemainingTraffic();
            $percentage = ($remaining / $subscription->total_traffic) * 100;
            
            if ($percentage < 20 && $percentage > 0) {
                $warningKey = "traffic_warning_{$subscription->id}";
                
                // Only send if not already warned in last 24 hours
                if (!Cache::has($warningKey)) {
                    SendTelegramNotification::dispatch(
                        $telegramId,
                        "⚠️ هشدار: حجم باقیمانده سرویس #{$subscription->id} شما کمتر از ۲۰٪ است.\n\n" .
                        "📊 باقیمانده: " . round($remaining / (1024 * 1024 * 1024), 2) . " GB"
                    );
                    
                    // Mark as warned for 24 hours
                    Cache::put($warningKey, true, self::WARNING_CACHE_TTL);
                }
            }
        }

        // Expiry warning (3 days remaining)
        if ($subscription->expire_date) {
            $daysRemaining = $subscription->getRemainingDays();
            
            if ($daysRemaining !== null && $daysRemaining <= 3 && $daysRemaining > 0) {
                $warningKey = "expiry_warning_{$subscription->id}_{$daysRemaining}";
                
                // Only send if not already warned for this specific day
                if (!Cache::has($warningKey)) {
                    $expireText = $daysRemaining == 1 ? "فردا" : "{$daysRemaining} روز دیگر";
                    
                    SendTelegramNotification::dispatch(
                        $telegramId,
                        "⏰ سرویس #{$subscription->id} شما {$expireText} منقضی می‌شود.\n\n" .
                        "📅 تاریخ انقضا: " . $subscription->expire_date->format('Y-m-d') . "\n\n" .
                        "لطفاً سرویس را تمدید کنید."
                    );
                    
                    // Mark as warned for this specific day
                    Cache::put($warningKey, true, self::WARNING_CACHE_TTL);
                }
            }
        }
    }

    /**
     * Check and expire subscriptions
     */
    public function expireSubscriptions(): void
    {
        $subscriptions = Subscription::where('status', 'active')
            ->where(function ($query) {
                $query->where('expire_date', '<=', now())
                    ->orWhere(function ($q) {
                        $q->whereColumn('used_traffic', '>=', 'total_traffic')
                            ->where('total_traffic', '>', 0);
                    });
            })
            ->with('user')
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                if ($subscription->isExpired()) {
                    $this->disableSubscription($subscription);
                    
                    // Notify user about expiration
                    if ($subscription->user && $subscription->user->telegram_id) {
                        $reason = $subscription->expire_date && $subscription->expire_date->isPast() 
                            ? "پایان زمان اعتبار" 
                            : "اتمام حجم ترافیک";
                            
                        SendTelegramNotification::dispatch(
                            $subscription->user->telegram_id,
                            "❌ سرویس #{$subscription->id} شما منقضی شد.\n\n" .
                            "📝 دلیل: {$reason}\n\n" .
                            "برای تمدید، به بخش «سرویس‌های من» مراجعه کنید."
                        );
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to expire subscription {$subscription->id}: " . $e->getMessage());
            }
        }
    }

    /**
     * Disable expired subscription
     */
    public function disableSubscription(Subscription $subscription): void
    {
        if ($subscription->isMultiServer()) {
            $this->multiServerService->disableOnAllServers($subscription);
        } elseif ($subscription->server) {
            $panelService = $this->panelFactory->make($subscription->server);
            $identifier = $subscription->server->isHiddify()
                ? ($subscription->panel_username ?? $subscription->uuid)
                : $subscription->marzban_username;
            $panelService->disableUser($subscription->server, $identifier);
        }

        $subscription->update(['status' => 'expired']);
        
        // Clear any warning cache for this subscription
        Cache::forget("traffic_warning_{$subscription->id}");
        Cache::forget("expiry_warning_{$subscription->id}_1");
        Cache::forget("expiry_warning_{$subscription->id}_2");
        Cache::forget("expiry_warning_{$subscription->id}_3");
    }

    /**
     * Cleanup old expired subscriptions (30 days)
     */
    public function cleanupExpiredSubscriptions(): void
    {
        $cutoffDate = Carbon::now()->subDays(30);
        
        $subscriptions = Subscription::where('status', 'expired')
            ->where('updated_at', '<', $cutoffDate)
            ->get();

        foreach ($subscriptions as $subscription) {
            try {
                if ($subscription->isMultiServer()) {
                    $this->multiServerService->deleteFromAllServers($subscription);
                } elseif ($subscription->server) {
                    $panelService = $this->panelFactory->make($subscription->server);
                    $identifier = $subscription->server->isHiddify()
                        ? ($subscription->panel_username ?? $subscription->uuid)
                        : $subscription->marzban_username;
                    $panelService->deleteUser($subscription->server, $identifier);
                }

                // Delete subscription links
                $subscription->subscriptionLinks()->delete();
                
                // Delete subscription
                $subscription->delete();
                
                Log::info("Cleaned up expired subscription {$subscription->id}");
            } catch (\Exception $e) {
                Log::error("Failed to cleanup subscription {$subscription->id}: " . $e->getMessage());
            }
        }
    }
}
