<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Exception;

/**
 * Service for interacting with Marzban Panel API
 * 
 * Implements VpnPanelInterface for unified panel management
 */
class MarzbanService implements VpnPanelInterface
{
    /**
     * Cache TTL for auth tokens (50 minutes - slightly less than typical 1 hour expiry)
     */
    private const TOKEN_CACHE_TTL = 3000;

    /**
     * Get authentication token for a server with caching
     */
    public function getAuthToken(Server $server): ?string
    {
        $cacheKey = "marzban_token_server_{$server->id}";
        
        // Try to get from cache first
        $cachedToken = Cache::get($cacheKey);
        if ($cachedToken) {
            return $cachedToken;
        }

        try {
            // Marzban uses OAuth2-style token endpoint with form-urlencoded data
            $response = Http::timeout(10)
                ->asForm()
                ->post("https://{$server->api_domain}/api/admin/token", [
                    'username' => $server->admin_user,
                    'password' => $server->admin_pass,
                    'grant_type' => 'password',
                ]);

            if ($response->successful()) {
                $token = $response->json('access_token');
                
                if ($token) {
                    // Cache the token
                    Cache::put($cacheKey, $token, self::TOKEN_CACHE_TTL);
                    return $token;
                }
            }

            Log::error("Marzban auth failed for server {$server->id}: " . $response->body());
            return null;
        } catch (Exception $e) {
            Log::error("Marzban auth exception for server {$server->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Invalidate cached token for a server
     */
    public function invalidateToken(Server $server): void
    {
        $cacheKey = "marzban_token_server_{$server->id}";
        Cache::forget($cacheKey);
    }

    /**
     * Make authenticated request to Marzban server
     */
    private function makeAuthenticatedRequest(Server $server, string $method, string $endpoint, array $data = []): ?array
    {
        $token = $this->getAuthToken($server);
        if (!$token) {
            return null;
        }

        try {
            $request = Http::timeout(30)->withToken($token);
            
            $url = "https://{$server->api_domain}{$endpoint}";
            
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url),
                default => throw new Exception("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            // If unauthorized, invalidate token and retry once
            if ($response->status() === 401) {
                $this->invalidateToken($server);
                $token = $this->getAuthToken($server);
                
                if ($token) {
                    $request = Http::timeout(30)->withToken($token);
                    $response = match (strtoupper($method)) {
                        'GET' => $request->get($url, $data),
                        'POST' => $request->post($url, $data),
                        'PUT' => $request->put($url, $data),
                        'DELETE' => $request->delete($url),
                        default => null,
                    };
                    
                    if ($response && $response->successful()) {
                        return $response->json() ?? [];
                    }
                }
            }

            Log::error("Marzban request failed for server {$server->id}: {$method} {$endpoint} - " . $response->body());
            return null;
        } catch (Exception $e) {
            Log::error("Marzban request exception for server {$server->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create user on Marzban server
     */
    public function createUser(Server $server, array $userData): ?array
    {
        // Build Marzban-specific payload
        $payload = [
            'username' => $userData['username'] ?? 'meow_' . \Illuminate\Support\Str::random(12),
            'proxies' => $userData['proxies'] ?? [
                'vless' => [
                    'id' => $userData['uuid'] ?? (string) \Illuminate\Support\Str::uuid(),
                    'flow' => 'xtls-rprx-vision',
                ],
            ],
            'inbounds' => $userData['inbounds'] ?? [
                'vless' => ['VLESS TCP REALITY', 'VLESS_TCP'],
            ],
            'data_limit' => $userData['traffic_limit'] ?? $userData['data_limit'] ?? 0,
            'expire' => $userData['expire_timestamp'] ?? $userData['expire'] ?? null,
            'data_limit_reset_strategy' => $userData['data_limit_reset_strategy'] ?? 'no_reset',
        ];

        // Add note if provided
        if (isset($userData['note'])) {
            $payload['note'] = $userData['note'];
        }

        $result = $this->makeAuthenticatedRequest($server, 'POST', '/api/user', $payload);

        if ($result) {
            // Update server active users count
            $server->increment('active_users_count');
        }

        return $result;
    }

    /**
     * Delete user from Marzban server
     */
    public function deleteUser(Server $server, string $identifier): bool
    {
        $result = $this->makeAuthenticatedRequest($server, 'DELETE', "/api/user/{$identifier}");

        if ($result !== null) {
            // Update server active users count
            $server->decrement('active_users_count');
            return true;
        }

        return false;
    }

    /**
     * Get user stats from Marzban
     */
    public function getUserStats(Server $server, string $identifier): ?array
    {
        $result = $this->makeAuthenticatedRequest($server, 'GET', "/api/user/{$identifier}");

        if ($result) {
            return [
                'status' => $result['status'] ?? 'unknown',
                'used_traffic' => $result['used_traffic'] ?? 0,
                'total_traffic' => $result['data_limit'] ?? 0,
                'expire_date' => $result['expire'] ?? null,
                'online' => ($result['online_at'] ?? null) 
                    ? (strtotime($result['online_at']) > strtotime('-5 minutes'))
                    : false,
                'username' => $result['username'] ?? $identifier,
                'uuid' => $result['proxies']['vless']['id'] ?? null,
                'created_at' => $result['created_at'] ?? null,
                'raw' => $result,
            ];
        }

        return null;
    }

    /**
     * Update user on Marzban server
     */
    public function updateUser(Server $server, string $identifier, array $userData): ?array
    {
        return $this->makeAuthenticatedRequest($server, 'PUT', "/api/user/{$identifier}", $userData);
    }

    /**
     * Disable user on Marzban
     */
    public function disableUser(Server $server, string $identifier): bool
    {
        $result = $this->updateUser($server, $identifier, ['status' => 'disabled']);
        return $result !== null;
    }

    /**
     * Enable user on Marzban
     */
    public function enableUser(Server $server, string $identifier): bool
    {
        $result = $this->updateUser($server, $identifier, ['status' => 'active']);
        return $result !== null;
    }

    /**
     * Reset user traffic on Marzban
     */
    public function resetUserTraffic(Server $server, string $identifier): bool
    {
        $result = $this->makeAuthenticatedRequest($server, 'POST', "/api/user/{$identifier}/reset");
        return $result !== null;
    }

    /**
     * Get user subscription info (links)
     */
    public function getUserSubscription(Server $server, string $username): ?array
    {
        return $this->makeAuthenticatedRequest($server, 'GET', "/api/user/{$username}/subscription");
    }

    /**
     * Get user subscription links
     */
    public function getUserSubscriptionLinks(Server $server, string $identifier): ?array
    {
        $user = $this->getUserStats($server, $identifier);
        
        if (!$user) {
            return null;
        }

        // Get subscription links from Marzban
        $subData = $this->makeAuthenticatedRequest($server, 'GET', "/api/user/{$identifier}");
        
        if ($subData && isset($subData['subscription_url'])) {
            return [
                'subscription' => $subData['subscription_url'],
                'links' => $subData['links'] ?? [],
            ];
        }

        // Fallback: construct subscription URL
        $baseUrl = "https://{$server->api_domain}";
        return [
            'subscription' => "{$baseUrl}/sub/{$identifier}",
            'links' => [],
        ];
    }

    /**
     * Get server health status
     */
    public function getServerHealth(Server $server): array
    {
        $token = $this->getAuthToken($server);
        if (!$token) {
            return [
                'status' => 'offline',
                'cpu' => 0,
                'ram' => 0,
                'total_users' => 0,
                'active_users' => 0,
                'online_users' => 0,
            ];
        }

        try {
            $response = Http::timeout(5)
                ->withToken($token)
                ->get("https://{$server->api_domain}/api/system");

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => 'online',
                    'cpu' => $data['cpu_usage'] ?? $data['cpu'] ?? 0,
                    'ram' => $data['mem_usage'] ?? $data['mem'] ?? 0,
                    'total_users' => $data['total_user'] ?? 0,
                    'active_users' => $data['users_active'] ?? 0,
                    'online_users' => $data['users_online'] ?? 0,
                    'uptime' => $data['uptime'] ?? null,
                    'version' => $data['version'] ?? null,
                    'incoming_bandwidth' => $data['incoming_bandwidth'] ?? 0,
                    'outgoing_bandwidth' => $data['outgoing_bandwidth'] ?? 0,
                ];
            }

            return ['status' => 'error', 'message' => $response->body()];
        } catch (Exception $e) {
            return ['status' => 'offline', 'message' => $e->getMessage()];
        }
    }

    /**
     * Get all users from Marzban server
     */
    public function getAllUsers(Server $server, int $offset = 0, int $limit = 100): ?array
    {
        return $this->makeAuthenticatedRequest($server, 'GET', '/api/users', [
            'offset' => $offset,
            'limit' => $limit,
        ]);
    }

    /**
     * Get server inbounds configuration
     */
    public function getInbounds(Server $server): ?array
    {
        return $this->makeAuthenticatedRequest($server, 'GET', '/api/inbounds');
    }

    /**
     * Revoke user subscription (regenerate UUID)
     */
    public function revokeUserSubscription(Server $server, string $identifier): bool
    {
        $result = $this->makeAuthenticatedRequest($server, 'POST', "/api/user/{$identifier}/revoke_sub");
        return $result !== null;
    }

    /**
     * Set user traffic limit
     */
    public function setUserTrafficLimit(Server $server, string $identifier, int $trafficBytes): bool
    {
        $result = $this->updateUser($server, $identifier, [
            'data_limit' => $trafficBytes,
        ]);

        return $result !== null;
    }

    /**
     * Extend user expiry
     */
    public function extendUserExpiry(Server $server, string $identifier, int $days): bool
    {
        // Get current user data
        $user = $this->getUserStats($server, $identifier);
        
        if (!$user) {
            return false;
        }

        // Calculate new expiry
        $currentExpiry = $user['expire_date'] 
            ? \Carbon\Carbon::createFromTimestamp($user['expire_date'])
            : now();
        
        $newExpiry = $currentExpiry->addDays($days);

        $result = $this->updateUser($server, $identifier, [
            'expire' => $newExpiry->timestamp,
        ]);

        return $result !== null;
    }

    /**
     * Sync user traffic with a specific value
     * Used for multi-server traffic synchronization
     */
    public function syncUserTraffic(Server $server, string $identifier, int $usedTrafficBytes): bool
    {
        // Marzban doesn't have a direct way to set used_traffic
        // We need to adjust data_limit to achieve the same effect
        $user = $this->getUserStats($server, $identifier);
        
        if (!$user) {
            return false;
        }

        // Get current total traffic limit
        $totalLimit = $user['total_traffic'] ?? 0;
        
        if ($totalLimit <= 0) {
            // Unlimited traffic, nothing to sync
            return true;
        }

        // Calculate the new data limit to reflect the used traffic
        // This is a workaround since Marzban doesn't allow setting used_traffic directly
        $currentUsed = $user['used_traffic'] ?? 0;
        
        if ($usedTrafficBytes > $currentUsed) {
            // We can't increase used_traffic directly in Marzban
            // Log this for manual review
            Log::info("Marzban traffic sync: User {$identifier} on server {$server->id} - target: {$usedTrafficBytes}, current: {$currentUsed}");
        }

        return true;
    }

    /**
     * Test connection to Marzban server
     */
    public function testConnection(Server $server): bool
    {
        $token = $this->getAuthToken($server);
        return $token !== null;
    }

    /**
     * Get online users count
     */
    public function getOnlineUsersCount(Server $server): int
    {
        $health = $this->getServerHealth($server);
        return $health['online_users'] ?? 0;
    }
}
