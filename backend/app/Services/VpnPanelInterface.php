<?php

namespace App\Services;

use App\Models\Server;

/**
 * Interface for VPN Panel Services (Marzban, Hiddify, etc.)
 * 
 * This interface defines the contract for all VPN panel implementations
 * to ensure consistent behavior across different panel types.
 */
interface VpnPanelInterface
{
    /**
     * Create a user on the VPN panel
     *
     * @param Server $server The server to create the user on
     * @param array $userData User data including:
     *   - username: string
     *   - uuid: string (for VLESS)
     *   - traffic_limit: int (bytes, 0 for unlimited)
     *   - expire_timestamp: int|null
     *   - max_devices: int
     * @return array|null The created user data or null on failure
     */
    public function createUser(Server $server, array $userData): ?array;

    /**
     * Delete a user from the VPN panel
     *
     * @param Server $server The server to delete from
     * @param string $identifier User identifier (username for Marzban, UUID for Hiddify)
     * @return bool Success status
     */
    public function deleteUser(Server $server, string $identifier): bool;

    /**
     * Get user statistics from the panel
     *
     * @param Server $server The server to query
     * @param string $identifier User identifier
     * @return array|null User stats including:
     *   - status: string (active, disabled, expired)
     *   - used_traffic: int (bytes)
     *   - total_traffic: int (bytes)
     *   - expire_date: int|null (timestamp)
     *   - online: bool
     */
    public function getUserStats(Server $server, string $identifier): ?array;

    /**
     * Update user on the VPN panel
     *
     * @param Server $server The server to update on
     * @param string $identifier User identifier
     * @param array $userData Data to update
     * @return array|null Updated user data or null on failure
     */
    public function updateUser(Server $server, string $identifier, array $userData): ?array;

    /**
     * Disable a user on the panel
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @return bool Success status
     */
    public function disableUser(Server $server, string $identifier): bool;

    /**
     * Enable a user on the panel
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @return bool Success status
     */
    public function enableUser(Server $server, string $identifier): bool;

    /**
     * Reset user traffic on the panel
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @return bool Success status
     */
    public function resetUserTraffic(Server $server, string $identifier): bool;

    /**
     * Get server health/status information
     *
     * @param Server $server The server to check
     * @return array Server health data including:
     *   - status: string (online, offline, error)
     *   - cpu: float (percentage)
     *   - ram: float (percentage)
     *   - total_users: int
     *   - active_users: int
     *   - online_users: int
     *   - version: string|null
     *   - uptime: int|null (seconds)
     */
    public function getServerHealth(Server $server): array;

    /**
     * Get all users from the panel
     *
     * @param Server $server The server to query
     * @param int $offset Pagination offset
     * @param int $limit Pagination limit
     * @return array|null List of users or null on failure
     */
    public function getAllUsers(Server $server, int $offset = 0, int $limit = 100): ?array;

    /**
     * Get user subscription links
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @return array|null Array of subscription links
     */
    public function getUserSubscriptionLinks(Server $server, string $identifier): ?array;

    /**
     * Revoke user subscription (regenerate keys)
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @return bool Success status
     */
    public function revokeUserSubscription(Server $server, string $identifier): bool;

    /**
     * Set user traffic limit
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @param int $trafficBytes Traffic limit in bytes (0 for unlimited)
     * @return bool Success status
     */
    public function setUserTrafficLimit(Server $server, string $identifier, int $trafficBytes): bool;

    /**
     * Extend user expiration
     *
     * @param Server $server The server
     * @param string $identifier User identifier
     * @param int $days Number of days to add
     * @return bool Success status
     */
    public function extendUserExpiry(Server $server, string $identifier, int $days): bool;

    /**
     * Get inbound configurations
     *
     * @param Server $server The server
     * @return array|null Inbound configurations
     */
    public function getInbounds(Server $server): ?array;

    /**
     * Restart the panel/core service (if supported).
     *
     * @param Server $server The server
     * @return bool True if restart was triggered, false if not supported
     */
    public function restartPanel(Server $server): bool;
}

