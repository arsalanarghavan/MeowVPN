<?php

namespace App\Services;

use App\Models\Server;
use Illuminate\Support\Collection;

class ServerSelectionService
{
    /**
     * Get available servers for a location tag
     */
    public function getAvailableServers(?string $locationTag = null): Collection
    {
        $query = Server::where('is_active', true)
            ->whereColumn('active_users_count', '<', 'capacity');

        if ($locationTag) {
            $query->where('location_tag', $locationTag);
        }

        return $query->get();
    }

    /**
     * Get available location tags
     */
    public function getAvailableLocations(): Collection
    {
        return Server::where('is_active', true)
            ->whereColumn('active_users_count', '<', 'capacity')
            ->select('location_tag', 'flag_emoji')
            ->distinct()
            ->get()
            ->map(function ($server) {
                return [
                    'tag' => $server->location_tag,
                    'emoji' => $server->flag_emoji,
                ];
            });
    }

    /**
     * Select best server for a location using Least Connection Algorithm
     */
    public function selectBestServer(string $locationTag): ?Server
    {
        $servers = Server::where('is_active', true)
            ->where('location_tag', $locationTag)
            ->whereColumn('active_users_count', '<', 'capacity')
            ->orderBy('active_users_count', 'asc')
            ->first();

        return $servers;
    }

    /**
     * Get all active servers for multi-server provisioning
     */
    public function getAllActiveServers(): Collection
    {
        return Server::where('is_active', true)
            ->whereColumn('active_users_count', '<', 'capacity')
            ->get();
    }
}

