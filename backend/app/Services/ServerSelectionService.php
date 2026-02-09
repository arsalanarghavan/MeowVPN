<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Collection;

class ServerSelectionService
{
    /**
     * Get available servers with optional filters
     */
    public function getAvailableServers(
        ?string $locationTag = null,
        ?string $region = null,
        ?string $serverCategory = null
    ): Collection {
        $query = Server::where('is_active', true)
            ->whereColumn('active_users_count', '<', 'capacity');

        if ($locationTag) {
            $query->where('location_tag', $locationTag);
        }
        if ($region) {
            $query->where('region', $region);
        }
        if ($serverCategory) {
            $query->where('server_category', $serverCategory);
        }

        return $query->get();
    }

    /**
     * Get available location tags (with optional region/category filter)
     */
    public function getAvailableLocations(?string $region = null, ?string $serverCategory = null): Collection
    {
        $query = Server::where('is_active', true)
            ->whereColumn('active_users_count', '<', 'capacity')
            ->select('location_tag', 'flag_emoji');

        if ($region) {
            $query->where('region', $region);
        }
        if ($serverCategory) {
            $query->where('server_category', $serverCategory);
        }

        return $query->distinct()->get()->map(function ($server) {
            return [
                'tag' => $server->location_tag,
                'emoji' => $server->flag_emoji,
            ];
        });
    }

    /**
     * Select best server for a location using Least Connection Algorithm
     */
    public function selectBestServer(string $locationTag, ?string $serverCategory = null): ?Server
    {
        $query = Server::where('is_active', true)
            ->where('location_tag', $locationTag)
            ->whereColumn('active_users_count', '<', 'capacity');

        if ($serverCategory) {
            $query->where('server_category', $serverCategory);
        }

        return $query->orderBy('active_users_count', 'asc')->first();
    }

    /**
     * Get all active servers for multi-server provisioning (optional filter by category)
     */
    public function getAllActiveServers(?string $serverCategory = null): Collection
    {
        $query = Server::where('is_active', true)
            ->whereColumn('active_users_count', '<', 'capacity');

        if ($serverCategory) {
            $query->where('server_category', $serverCategory);
        }

        return $query->get();
    }

    /**
     * Get servers by category for tunnel (tunnel_entry + tunnel_exit) or direct
     */
    public function getServersByCategory(string $serverCategory): Collection
    {
        return $this->getAvailableServers(null, null, $serverCategory);
    }

    /**
     * Get tunnel entry servers (Iran)
     */
    public function getTunnelEntryServers(): Collection
    {
        return $this->getAvailableServers(null, Server::REGION_IRAN, Server::CATEGORY_TUNNEL_ENTRY);
    }

    /**
     * Get tunnel exit servers (Foreign)
     */
    public function getTunnelExitServers(): Collection
    {
        return $this->getAvailableServers(null, Server::REGION_FOREIGN, Server::CATEGORY_TUNNEL_EXIT);
    }

    /**
     * Get direct servers (Foreign)
     */
    public function getDirectServers(): Collection
    {
        return $this->getAvailableServers(null, Server::REGION_FOREIGN, Server::CATEGORY_DIRECT);
    }
}

