<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Server;
use App\Models\Plan;
use App\Models\SubscriptionLink;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Service for managing multi-server subscriptions
 * 
 * Supports both Marzban and Hiddify panels through VpnPanelFactory
 */
class MultiServerProvisioningService
{
    public function __construct(
        private VpnPanelFactory $panelFactory
    ) {}

    /**
     * Create subscription on multiple servers
     * Supports mixed panel types (Marzban and Hiddify servers together)
     */
    public function createMultiServerSubscription(
        int $userId,
        int $planId,
        array $serverIds,
        int $maxDevices = 1
    ): ?Subscription {
        return DB::transaction(function () use ($userId, $planId, $serverIds, $maxDevices) {
            $plan = Plan::findOrFail($planId);
            
            // Generate unique username and UUID
            $username = 'meow_' . Str::random(12);
            $uuid = (string) Str::uuid();
            
            // Calculate expiry
            $expireDate = $plan->duration_days > 0 
                ? now()->addDays($plan->duration_days) 
                : null;

            // Use plan's max_devices if not explicitly specified
            $maxDevices = $maxDevices > 0 ? $maxDevices : ($plan->max_devices ?? 1);

            // Create main subscription record
            $subscription = Subscription::create([
                'user_id' => $userId,
                'plan_id' => $planId,
                'server_id' => null, // null for multi-server
                'uuid' => $uuid,
                'marzban_username' => $username,
                'panel_username' => $uuid, // For Hiddify, we use UUID as identifier
                'status' => 'active',
                'total_traffic' => $plan->traffic_bytes,
                'used_traffic' => 0,
                'expire_date' => $expireDate,
                'max_devices' => $maxDevices,
            ]);

            $servers = Server::whereIn('id', $serverIds)
                ->where('is_active', true)
                ->get();

            $successfulServers = 0;

            foreach ($servers as $server) {
                try {
                    $result = $this->createUserOnServer(
                        $server,
                        $username,
                        $uuid,
                        $plan->traffic_bytes,
                        $expireDate?->timestamp,
                        $maxDevices
                    );

                    if ($result) {
                        $successfulServers++;
                        $this->storeSubscriptionLinks($subscription, $server, $result, $uuid);
                    }
                } catch (Exception $e) {
                    Log::error("Failed to create user on server {$server->id} ({$server->panel_type}): " . $e->getMessage());
                    // Continue with other servers
                }
            }

            if ($successfulServers === 0) {
                throw new Exception('خطا در ایجاد سرویس روی تمام سرورها');
            }

            return $subscription;
        });
    }

    /**
     * Create user on a specific server using the appropriate panel service
     */
    private function createUserOnServer(
        Server $server,
        string $username,
        string $uuid,
        int $trafficLimit,
        ?int $expireTimestamp,
        int $maxDevices
    ): ?array {
        $panelService = $this->panelFactory->make($server);

        $userData = [
            'username' => $username,
            'uuid' => $uuid,
            'traffic_limit' => $trafficLimit > 0 ? $trafficLimit : 0,
            'expire_timestamp' => $expireTimestamp,
            'max_devices' => $maxDevices,
            'comment' => 'MeowVPN Multi-Server Subscription',
        ];

        // For Marzban, add proxy configuration
        if ($server->isMarzban()) {
            $userData['proxies'] = [
                'vless' => [
                    'id' => $uuid,
                    'flow' => 'xtls-rprx-vision',
                ],
            ];
            $userData['inbounds'] = [
                'vless' => ['VLESS TCP REALITY', 'VLESS_TCP'],
            ];
            $userData['data_limit'] = $trafficLimit > 0 ? $trafficLimit : 0;
            $userData['expire'] = $expireTimestamp;
            $userData['data_limit_reset_strategy'] = 'no_reset';
        }

        return $panelService->createUser($server, $userData);
    }

    /**
     * Store subscription links from panel response
     */
    private function storeSubscriptionLinks(Subscription $subscription, Server $server, array $result, string $uuid): void
    {
        $linksStored = false;

        // Try to extract links from result
        if (isset($result['links'])) {
            if (is_array($result['links'])) {
                foreach ($result['links'] as $protocol => $links) {
                    if (is_array($links)) {
                        foreach ($links as $link) {
                            if (is_string($link)) {
                                SubscriptionLink::create([
                                    'subscription_id' => $subscription->id,
                                    'server_id' => $server->id,
                                    'vless_link' => $link,
                                ]);
                                $linksStored = true;
                            }
                        }
                    } elseif (is_string($links)) {
                        SubscriptionLink::create([
                            'subscription_id' => $subscription->id,
                            'server_id' => $server->id,
                            'vless_link' => $links,
                        ]);
                        $linksStored = true;
                    }
                }
            }
        }

        // If no links extracted, generate based on panel type
        if (!$linksStored) {
            $link = $this->generateSubscriptionLinkForServer($server, $uuid, $subscription->marzban_username);
            SubscriptionLink::create([
                'subscription_id' => $subscription->id,
                'server_id' => $server->id,
                'vless_link' => $link,
            ]);
        }
    }

    /**
     * Generate subscription link for a specific server
     */
    private function generateSubscriptionLinkForServer(Server $server, string $uuid, string $username): string
    {
        if ($server->isHiddify()) {
            // Hiddify subscription link format
            return "https://{$server->api_domain}/{$uuid}/all.txt";
        }

        // Marzban VLESS link format
        $domain = $server->api_domain;
        $serverName = $server->name;
        
        return sprintf(
            "vless://%s@%s:443?type=tcp&security=reality&pbk=%s&fp=chrome&sni=%s&sid=%s&spx=%%2F#%s",
            $uuid,
            $domain,
            config('services.marzban.reality_public_key', ''),
            config('services.marzban.reality_sni', $domain),
            config('services.marzban.reality_short_id', ''),
            urlencode($serverName . ' - MeowVPN')
        );
    }

    /**
     * Generate main subscription link for multi-server subscription
     */
    public function generateSubscriptionLink(Subscription $subscription): string
    {
        $domain = config('app.subscription_domain', config('app.url'));
        return "{$domain}/api/sub/{$subscription->uuid}";
    }

    /**
     * Add a new server to an existing multi-server subscription
     */
    public function addServerToSubscription(Subscription $subscription, Server $server): bool
    {
        try {
            $remainingTraffic = $subscription->total_traffic - $subscription->used_traffic;
            
            $result = $this->createUserOnServer(
                $server,
                $subscription->marzban_username,
                $subscription->uuid,
                $remainingTraffic > 0 ? $remainingTraffic : 0,
                $subscription->expire_date?->timestamp,
                $subscription->max_devices ?? 1
            );

            if ($result) {
                $this->storeSubscriptionLinks($subscription, $server, $result, $subscription->uuid);
                return true;
            }

            return false;
        } catch (Exception $e) {
            Log::error("Failed to add server to subscription: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove a server from a multi-server subscription
     */
    public function removeServerFromSubscription(Subscription $subscription, Server $server): bool
    {
        try {
            $panelService = $this->panelFactory->make($server);
            
            // Get the appropriate identifier for the panel
            $identifier = $server->isHiddify() 
                ? ($subscription->panel_username ?? $subscription->uuid)
                : $subscription->marzban_username;

            // Delete from panel
            $panelService->deleteUser($server, $identifier);

            // Delete subscription links for this server
            SubscriptionLink::where('subscription_id', $subscription->id)
                ->where('server_id', $server->id)
                ->delete();

            return true;
        } catch (Exception $e) {
            Log::error("Failed to remove server from subscription: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Renew a multi-server subscription
     * Extends expiry on all servers
     */
    public function renewMultiServerSubscription(Subscription $subscription, int $daysToAdd, int $trafficToAdd = 0): bool
    {
        $servers = $subscription->getServers();
        $successCount = 0;

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                // Extend expiry
                if ($panelService->extendUserExpiry($server, $identifier, $daysToAdd)) {
                    $successCount++;
                }

                // Update traffic if needed
                if ($trafficToAdd > 0) {
                    $newTrafficLimit = $subscription->total_traffic + $trafficToAdd;
                    $panelService->setUserTrafficLimit($server, $identifier, $newTrafficLimit);
                }
            } catch (Exception $e) {
                Log::error("Failed to renew on server {$server->id}: " . $e->getMessage());
            }
        }

        return $successCount > 0;
    }

    /**
     * Sync traffic usage across all servers in a multi-server subscription
     * Finds the maximum used traffic and syncs to all servers
     */
    public function syncTrafficAcrossServers(Subscription $subscription): bool
    {
        if (!$subscription->isMultiServer()) {
            return false;
        }

        $servers = $subscription->getServers();
        $maxUsedTraffic = 0;

        // First pass: collect traffic usage from all servers
        $usageData = [];
        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                $stats = $panelService->getUserStats($server, $identifier);
                
                if ($stats && isset($stats['used_traffic'])) {
                    $usedTraffic = (int) $stats['used_traffic'];
                    $usageData[$server->id] = $usedTraffic;
                    
                    if ($usedTraffic > $maxUsedTraffic) {
                        $maxUsedTraffic = $usedTraffic;
                    }
                }
            } catch (Exception $e) {
                Log::error("Failed to get traffic for server {$server->id}: " . $e->getMessage());
            }
        }

        // Second pass: sync max traffic to all servers (for Hiddify only, as Marzban doesn't support this)
        foreach ($servers as $server) {
            if ($server->isHiddify()) {
                try {
                    $panelService = $this->panelFactory->make($server);
                    $identifier = $subscription->panel_username ?? $subscription->uuid;
                    
                    $currentUsage = $usageData[$server->id] ?? 0;
                    
                    if ($currentUsage < $maxUsedTraffic) {
                        // Sync to max usage
                        $panelService->syncUserTraffic($server, $identifier, $maxUsedTraffic);
                    }
                } catch (Exception $e) {
                    Log::error("Failed to sync traffic on server {$server->id}: " . $e->getMessage());
                }
            }
        }

        // Update subscription's used_traffic to max
        $subscription->update(['used_traffic' => $maxUsedTraffic]);

        return true;
    }

    /**
     * Enable subscription on all servers
     */
    public function enableOnAllServers(Subscription $subscription): bool
    {
        $servers = $subscription->getServers();
        $successCount = 0;

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                if ($panelService->enableUser($server, $identifier)) {
                    $successCount++;
                }
            } catch (Exception $e) {
                Log::error("Failed to enable on server {$server->id}: " . $e->getMessage());
            }
        }

        return $successCount > 0;
    }

    /**
     * Disable subscription on all servers
     */
    public function disableOnAllServers(Subscription $subscription): bool
    {
        $servers = $subscription->getServers();
        $successCount = 0;

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                if ($panelService->disableUser($server, $identifier)) {
                    $successCount++;
                }
            } catch (Exception $e) {
                Log::error("Failed to disable on server {$server->id}: " . $e->getMessage());
            }
        }

        return $successCount > 0;
    }

    /**
     * Delete subscription from all servers
     */
    public function deleteFromAllServers(Subscription $subscription): bool
    {
        $servers = $subscription->getServers();
        $successCount = 0;

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                if ($panelService->deleteUser($server, $identifier)) {
                    $successCount++;
                }
            } catch (Exception $e) {
                Log::error("Failed to delete from server {$server->id}: " . $e->getMessage());
            }
        }

        // Delete all subscription links
        $subscription->subscriptionLinks()->delete();

        return $successCount > 0;
    }

    /**
     * Update max devices on all servers
     */
    public function updateMaxDevicesOnAllServers(Subscription $subscription, int $maxDevices): bool
    {
        $servers = $subscription->getServers();
        $successCount = 0;

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                $result = $panelService->updateUser($server, $identifier, [
                    'max_devices' => $maxDevices,
                ]);

                if ($result !== null) {
                    $successCount++;
                }
            } catch (Exception $e) {
                Log::error("Failed to update max_devices on server {$server->id}: " . $e->getMessage());
            }
        }

        // Update subscription record
        $subscription->update(['max_devices' => $maxDevices]);

        return $successCount > 0;
    }

    /**
     * Get aggregated stats from all servers
     */
    public function getAggregatedStats(Subscription $subscription): array
    {
        $servers = $subscription->getServers();
        $stats = [
            'total_used_traffic' => 0,
            'max_used_traffic' => 0,
            'servers' => [],
            'online_count' => 0,
        ];

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                
                $identifier = $server->isHiddify() 
                    ? ($subscription->panel_username ?? $subscription->uuid)
                    : $subscription->marzban_username;

                $serverStats = $panelService->getUserStats($server, $identifier);
                
                if ($serverStats) {
                    $usedTraffic = (int) ($serverStats['used_traffic'] ?? 0);
                    
                    $stats['servers'][$server->id] = [
                        'name' => $server->name,
                        'used_traffic' => $usedTraffic,
                        'online' => $serverStats['online'] ?? false,
                        'status' => $serverStats['status'] ?? 'unknown',
                    ];
                    
                    $stats['total_used_traffic'] += $usedTraffic;
                    
                    if ($usedTraffic > $stats['max_used_traffic']) {
                        $stats['max_used_traffic'] = $usedTraffic;
                    }
                    
                    if ($serverStats['online'] ?? false) {
                        $stats['online_count']++;
                    }
                }
            } catch (Exception $e) {
                Log::error("Failed to get stats from server {$server->id}: " . $e->getMessage());
                $stats['servers'][$server->id] = [
                    'name' => $server->name,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $stats;
    }
}
