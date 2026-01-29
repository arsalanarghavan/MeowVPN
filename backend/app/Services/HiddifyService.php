<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Exception;

/**
 * Service for interacting with Hiddify Panel API
 * 
 * Hiddify API uses an API key for authentication and 
 * UUID-based user identification.
 */
class HiddifyService implements VpnPanelInterface
{
    /**
     * Cache TTL for API responses (5 minutes)
     */
    private const CACHE_TTL = 300;

    /**
     * Get the base URL for API requests
     */
    private function getBaseUrl(Server $server): string
    {
        return "https://{$server->api_domain}";
    }

    /**
     * Get API headers including authorization
     */
    private function getHeaders(Server $server): array
    {
        return [
            'Authorization' => 'Bearer ' . $server->api_key,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    /**
     * Make an authenticated request to Hiddify API
     */
    private function makeRequest(Server $server, string $method, string $endpoint, array $data = []): ?array
    {
        try {
            $url = $this->getBaseUrl($server) . $endpoint;
            $headers = $this->getHeaders($server);

            $request = Http::timeout(30)->withHeaders($headers);

            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'PATCH' => $request->patch($url, $data),
                'DELETE' => $request->delete($url),
                default => throw new Exception("Unsupported HTTP method: {$method}"),
            };

            if ($response->successful()) {
                return $response->json() ?? [];
            }

            Log::error("Hiddify API request failed for server {$server->id}: {$method} {$endpoint}", [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (Exception $e) {
            Log::error("Hiddify API exception for server {$server->id}: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a user on Hiddify panel
     */
    public function createUser(Server $server, array $userData): ?array
    {
        // Generate UUID if not provided
        $uuid = $userData['uuid'] ?? (string) Str::uuid();
        
        // Hiddify user payload
        $payload = [
            'uuid' => $uuid,
            'name' => $userData['username'] ?? 'meow_' . Str::random(8),
            'usage_limit_GB' => isset($userData['traffic_limit']) 
                ? round($userData['traffic_limit'] / (1024 * 1024 * 1024), 2) 
                : 0,
            'package_days' => $userData['duration_days'] ?? 30,
            'mode' => 'no_reset',
            'start_date' => now()->format('Y-m-d'),
            'current_usage_GB' => 0,
            'last_online' => null,
            'comment' => $userData['comment'] ?? 'Created by MeowVPN',
            'telegram_id' => $userData['telegram_id'] ?? null,
            'added_by_uuid' => null,
            'max_ips' => $userData['max_devices'] ?? 1,
            'enable' => true,
            'is_active' => true,
        ];

        // If expire timestamp is provided, calculate package days
        if (isset($userData['expire_timestamp']) && $userData['expire_timestamp']) {
            $expireDate = \Carbon\Carbon::createFromTimestamp($userData['expire_timestamp']);
            $payload['package_days'] = max(1, now()->diffInDays($expireDate));
        }

        $result = $this->makeRequest($server, 'POST', '/api/v2/admin/user/', $payload);

        if ($result) {
            // Update server active users count
            $server->increment('active_users_count');

            // Format response to standard format
            return $this->formatUserResponse($result, $server);
        }

        return null;
    }

    /**
     * Delete a user from Hiddify panel
     */
    public function deleteUser(Server $server, string $identifier): bool
    {
        $result = $this->makeRequest($server, 'DELETE', "/api/v2/admin/user/{$identifier}/");

        if ($result !== null) {
            $server->decrement('active_users_count');
            return true;
        }

        return false;
    }

    /**
     * Get user stats from Hiddify
     */
    public function getUserStats(Server $server, string $identifier): ?array
    {
        $result = $this->makeRequest($server, 'GET', "/api/v2/admin/user/{$identifier}/");

        if ($result) {
            return [
                'status' => $result['is_active'] ?? false ? 'active' : 'disabled',
                'used_traffic' => ($result['current_usage_GB'] ?? 0) * 1024 * 1024 * 1024,
                'total_traffic' => ($result['usage_limit_GB'] ?? 0) * 1024 * 1024 * 1024,
                'expire_date' => isset($result['start_date']) && isset($result['package_days'])
                    ? strtotime($result['start_date'] . ' + ' . $result['package_days'] . ' days')
                    : null,
                'online' => isset($result['last_online']) && $result['last_online'] 
                    ? (strtotime($result['last_online']) > strtotime('-5 minutes'))
                    : false,
                'last_online' => $result['last_online'] ?? null,
                'max_devices' => $result['max_ips'] ?? 1,
                'name' => $result['name'] ?? null,
                'uuid' => $result['uuid'] ?? $identifier,
            ];
        }

        return null;
    }

    /**
     * Update user on Hiddify panel
     */
    public function updateUser(Server $server, string $identifier, array $userData): ?array
    {
        $payload = [];

        // Map common fields to Hiddify format
        if (isset($userData['traffic_limit'])) {
            $payload['usage_limit_GB'] = round($userData['traffic_limit'] / (1024 * 1024 * 1024), 2);
        }

        if (isset($userData['expire'])) {
            $expireDate = \Carbon\Carbon::createFromTimestamp($userData['expire']);
            $payload['package_days'] = max(1, now()->diffInDays($expireDate));
        }

        if (isset($userData['status'])) {
            $payload['enable'] = $userData['status'] === 'active';
            $payload['is_active'] = $userData['status'] === 'active';
        }

        if (isset($userData['max_devices'])) {
            $payload['max_ips'] = $userData['max_devices'];
        }

        if (isset($userData['name'])) {
            $payload['name'] = $userData['name'];
        }

        if (isset($userData['data_limit'])) {
            $payload['usage_limit_GB'] = round($userData['data_limit'] / (1024 * 1024 * 1024), 2);
        }

        $result = $this->makeRequest($server, 'PATCH', "/api/v2/admin/user/{$identifier}/", $payload);

        if ($result) {
            return $this->formatUserResponse($result, $server);
        }

        return null;
    }

    /**
     * Disable a user on Hiddify
     */
    public function disableUser(Server $server, string $identifier): bool
    {
        $result = $this->updateUser($server, $identifier, [
            'status' => 'disabled',
        ]);

        return $result !== null;
    }

    /**
     * Enable a user on Hiddify
     */
    public function enableUser(Server $server, string $identifier): bool
    {
        $result = $this->updateUser($server, $identifier, [
            'status' => 'active',
        ]);

        return $result !== null;
    }

    /**
     * Reset user traffic on Hiddify
     */
    public function resetUserTraffic(Server $server, string $identifier): bool
    {
        // In Hiddify, resetting traffic means setting current_usage_GB to 0
        $result = $this->makeRequest($server, 'PATCH', "/api/v2/admin/user/{$identifier}/", [
            'current_usage_GB' => 0,
        ]);

        return $result !== null;
    }

    /**
     * Get server health/status information
     */
    public function getServerHealth(Server $server): array
    {
        try {
            // Try to get server status
            $result = $this->makeRequest($server, 'GET', '/api/v2/admin/server_status/');

            if ($result) {
                return [
                    'status' => 'online',
                    'cpu' => $result['cpu_percent'] ?? $result['cpu'] ?? 0,
                    'ram' => $result['memory_percent'] ?? $result['ram'] ?? 0,
                    'total_users' => $result['total_users'] ?? 0,
                    'active_users' => $result['active_users'] ?? 0,
                    'online_users' => $result['online_users'] ?? 0,
                    'version' => $result['version'] ?? null,
                    'uptime' => $result['uptime'] ?? null,
                    'total_traffic' => $result['total_traffic'] ?? 0,
                    'today_traffic' => $result['today_traffic'] ?? 0,
                ];
            }

            // Try alternative endpoint
            $adminResult = $this->makeRequest($server, 'GET', '/api/v2/admin/admin_info/');

            if ($adminResult) {
                return [
                    'status' => 'online',
                    'cpu' => $adminResult['cpu'] ?? 0,
                    'ram' => $adminResult['ram'] ?? 0,
                    'total_users' => $adminResult['users_count'] ?? 0,
                    'active_users' => $adminResult['active_users'] ?? 0,
                    'online_users' => $adminResult['online_count'] ?? 0,
                    'version' => $adminResult['version'] ?? null,
                    'uptime' => null,
                ];
            }

            return [
                'status' => 'error',
                'message' => 'Could not retrieve server status',
            ];
        } catch (Exception $e) {
            return [
                'status' => 'offline',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get all users from Hiddify panel
     */
    public function getAllUsers(Server $server, int $offset = 0, int $limit = 100): ?array
    {
        $result = $this->makeRequest($server, 'GET', '/api/v2/admin/user/', [
            'offset' => $offset,
            'limit' => $limit,
        ]);

        if ($result && isset($result['users'])) {
            return array_map(function ($user) use ($server) {
                return $this->formatUserResponse($user, $server);
            }, $result['users']);
        }

        // If result is directly an array of users
        if ($result && is_array($result)) {
            return array_map(function ($user) use ($server) {
                return $this->formatUserResponse($user, $server);
            }, $result);
        }

        return null;
    }

    /**
     * Get user subscription links
     */
    public function getUserSubscriptionLinks(Server $server, string $identifier): ?array
    {
        // Hiddify subscription link format
        $baseUrl = $this->getBaseUrl($server);
        
        // Get user to verify they exist
        $user = $this->getUserStats($server, $identifier);
        
        if (!$user) {
            return null;
        }

        // Generate subscription links
        $links = [];
        
        // Standard subscription link
        $links['subscription'] = "{$baseUrl}/{$identifier}/all.txt";
        
        // Protocol-specific links
        $links['vless'] = "{$baseUrl}/{$identifier}/vless";
        $links['vmess'] = "{$baseUrl}/{$identifier}/vmess";
        $links['trojan'] = "{$baseUrl}/{$identifier}/trojan";
        $links['clash'] = "{$baseUrl}/{$identifier}/clash";
        $links['singbox'] = "{$baseUrl}/{$identifier}/singbox";
        
        // Auto-detect link
        $links['auto'] = "{$baseUrl}/{$identifier}/auto";

        return $links;
    }

    /**
     * Revoke user subscription (regenerate UUID)
     */
    public function revokeUserSubscription(Server $server, string $identifier): bool
    {
        // In Hiddify, we need to update the user with a new UUID
        // This is typically not supported directly - user needs to be recreated
        // For now, we'll just return false as this requires special handling
        Log::warning("Hiddify: Revoke subscription not directly supported. User {$identifier} on server {$server->id}");
        
        return false;
    }

    /**
     * Set user traffic limit
     */
    public function setUserTrafficLimit(Server $server, string $identifier, int $trafficBytes): bool
    {
        $result = $this->updateUser($server, $identifier, [
            'traffic_limit' => $trafficBytes,
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

        $result = $this->makeRequest($server, 'PATCH', "/api/v2/admin/user/{$identifier}/", [
            'package_days' => now()->diffInDays($newExpiry),
        ]);

        return $result !== null;
    }

    /**
     * Get inbound configurations
     */
    public function getInbounds(Server $server): ?array
    {
        $result = $this->makeRequest($server, 'GET', '/api/v2/admin/inbounds/');

        return $result;
    }

    /**
     * Format user response to standard format
     */
    private function formatUserResponse(array $user, Server $server): array
    {
        $uuid = $user['uuid'] ?? null;
        $baseUrl = $this->getBaseUrl($server);

        return [
            'uuid' => $uuid,
            'username' => $user['name'] ?? $uuid,
            'status' => ($user['is_active'] ?? false) ? 'active' : 'disabled',
            'used_traffic' => ($user['current_usage_GB'] ?? 0) * 1024 * 1024 * 1024,
            'total_traffic' => ($user['usage_limit_GB'] ?? 0) * 1024 * 1024 * 1024,
            'expire_date' => isset($user['start_date']) && isset($user['package_days'])
                ? strtotime($user['start_date'] . ' + ' . $user['package_days'] . ' days')
                : null,
            'max_devices' => $user['max_ips'] ?? 1,
            'links' => $uuid ? [
                'subscription' => "{$baseUrl}/{$uuid}/all.txt",
                'vless' => "{$baseUrl}/{$uuid}/vless",
                'vmess' => "{$baseUrl}/{$uuid}/vmess",
                'clash' => "{$baseUrl}/{$uuid}/clash",
            ] : [],
            'online' => isset($user['last_online']) && $user['last_online']
                ? (strtotime($user['last_online']) > strtotime('-5 minutes'))
                : false,
        ];
    }

    /**
     * Test connection to Hiddify server
     */
    public function testConnection(Server $server): bool
    {
        $health = $this->getServerHealth($server);
        return $health['status'] === 'online';
    }

    /**
     * Sync user traffic with a specific value
     * Used for multi-server traffic synchronization
     */
    public function syncUserTraffic(Server $server, string $identifier, int $usedTrafficBytes): bool
    {
        $usedGB = round($usedTrafficBytes / (1024 * 1024 * 1024), 2);
        
        $result = $this->makeRequest($server, 'PATCH', "/api/v2/admin/user/{$identifier}/", [
            'current_usage_GB' => $usedGB,
        ]);

        return $result !== null;
    }

    /**
     * Get online users count
     */
    public function getOnlineUsersCount(Server $server): int
    {
        $health = $this->getServerHealth($server);
        return $health['online_users'] ?? 0;
    }

    /**
     * Get user by name
     */
    public function getUserByName(Server $server, string $name): ?array
    {
        $users = $this->getAllUsers($server, 0, 1000);
        
        if (!$users) {
            return null;
        }

        foreach ($users as $user) {
            if (($user['username'] ?? '') === $name || ($user['name'] ?? '') === $name) {
                return $user;
            }
        }

        return null;
    }
}

