<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Server;
use App\Services\ServerSelectionService;
use App\Services\VpnPanelFactory;

class ServerController extends Controller
{
    public function __construct(
        private ServerSelectionService $serverSelectionService,
        private VpnPanelFactory $panelFactory
    ) {}

    public function index(Request $request)
    {
        $query = Server::query();

        if ($request->has('location_tag')) {
            $query->where('location_tag', $request->location_tag);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('panel_type')) {
            $query->where('panel_type', $request->panel_type);
        }

        return response()->json($query->get());
    }

    public function available()
    {
        $servers = $this->serverSelectionService->getAvailableServers();
        $locations = $this->serverSelectionService->getAvailableLocations();

        // Convert Collections to arrays for consistent JSON response
        return response()->json([
            'servers' => $servers->toArray(),
            'locations' => $locations->toArray(),
        ]);
    }

    public function show(Server $server)
    {
        return response()->json($server);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string',
            'flag_emoji' => 'nullable|string',
            'ip_address' => 'required|ip',
            'api_domain' => 'required|string',
            'admin_user' => 'nullable|string', // Nullable for Hiddify
            'admin_pass' => 'nullable|string', // Nullable for Hiddify
            'api_key' => 'nullable|string', // For Hiddify
            'capacity' => 'required|integer|min:1',
            'type' => 'required|in:single,multi_relay',
            'location_tag' => 'required|string|max:10',
            'is_active' => 'boolean',
            'panel_type' => 'required|in:marzban,hiddify',
        ]);

        // Validate based on panel type
        if ($data['panel_type'] === 'marzban') {
            if (empty($data['admin_user']) || empty($data['admin_pass'])) {
                return response()->json([
                    'error' => 'admin_user و admin_pass برای پنل مرزبان الزامی است'
                ], 422);
            }
        } elseif ($data['panel_type'] === 'hiddify') {
            if (empty($data['api_key'])) {
                return response()->json([
                    'error' => 'api_key برای پنل هیدیفای الزامی است'
                ], 422);
            }
        }

        $server = Server::create($data);

        return response()->json($server, 201);
    }

    public function update(Request $request, Server $server)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'flag_emoji' => 'nullable|string',
            'ip_address' => 'sometimes|ip',
            'api_domain' => 'sometimes|string',
            'admin_user' => 'nullable|string',
            'admin_pass' => 'nullable|string',
            'api_key' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'type' => 'sometimes|in:single,multi_relay',
            'location_tag' => 'sometimes|string|max:10',
            'is_active' => 'boolean',
            'panel_type' => 'sometimes|in:marzban,hiddify',
        ]);

        $server->update($data);

        return response()->json($server);
    }

    public function destroy(Server $server)
    {
        $server->delete();

        return response()->json(['message' => 'Server deleted']);
    }

    /**
     * Get health status for a single server
     */
    public function health(Server $server)
    {
        $panelService = $this->panelFactory->make($server);
        $health = $panelService->getServerHealth($server);

        return response()->json($health);
    }

    /**
     * Get monitoring data for all servers
     */
    public function monitoring(Request $request)
    {
        $query = Server::query();

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $servers = $query->get();
        $monitoringData = [];

        foreach ($servers as $server) {
            try {
                $panelService = $this->panelFactory->make($server);
                $health = $panelService->getServerHealth($server);

                $monitoringData[] = [
                    'id' => $server->id,
                    'name' => $server->name,
                    'flag_emoji' => $server->flag_emoji,
                    'location_tag' => $server->location_tag,
                    'panel_type' => $server->panel_type,
                    'ip_address' => $server->ip_address,
                    'api_domain' => $server->api_domain,
                    'capacity' => $server->capacity,
                    'active_users_count' => $server->active_users_count,
                    'is_active' => $server->is_active,
                    'health' => $health,
                    'available_slots' => $server->getAvailableSlots(),
                    'usage_percentage' => $server->capacity > 0 
                        ? round(($server->active_users_count / $server->capacity) * 100, 2)
                        : 0,
                ];
            } catch (\Exception $e) {
                $monitoringData[] = [
                    'id' => $server->id,
                    'name' => $server->name,
                    'flag_emoji' => $server->flag_emoji,
                    'location_tag' => $server->location_tag,
                    'panel_type' => $server->panel_type,
                    'ip_address' => $server->ip_address,
                    'api_domain' => $server->api_domain,
                    'capacity' => $server->capacity,
                    'active_users_count' => $server->active_users_count,
                    'is_active' => $server->is_active,
                    'health' => [
                        'status' => 'error',
                        'message' => $e->getMessage(),
                    ],
                    'available_slots' => $server->getAvailableSlots(),
                    'usage_percentage' => 0,
                ];
            }
        }

        // Calculate summary stats
        $summary = [
            'total_servers' => count($servers),
            'online_servers' => count(array_filter($monitoringData, fn($s) => ($s['health']['status'] ?? '') === 'online')),
            'offline_servers' => count(array_filter($monitoringData, fn($s) => ($s['health']['status'] ?? '') !== 'online')),
            'total_capacity' => $servers->sum('capacity'),
            'total_active_users' => $servers->sum('active_users_count'),
            'marzban_servers' => $servers->where('panel_type', 'marzban')->count(),
            'hiddify_servers' => $servers->where('panel_type', 'hiddify')->count(),
        ];

        return response()->json([
            'servers' => $monitoringData,
            'summary' => $summary,
        ]);
    }

    /**
     * Test connection to a server
     */
    public function testConnection(Server $server)
    {
        try {
            $panelService = $this->panelFactory->make($server);
            $health = $panelService->getServerHealth($server);

            $isOnline = ($health['status'] ?? '') === 'online';

            return response()->json([
                'success' => $isOnline,
                'status' => $health['status'] ?? 'unknown',
                'message' => $isOnline ? 'اتصال موفق' : 'اتصال ناموفق',
                'details' => $health,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'status' => 'error',
                'message' => 'خطا در اتصال: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get inbound configurations for a server
     */
    public function inbounds(Server $server)
    {
        try {
            $panelService = $this->panelFactory->make($server);
            $inbounds = $panelService->getInbounds($server);

            return response()->json([
                'success' => true,
                'inbounds' => $inbounds,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get all users from a server
     */
    public function users(Request $request, Server $server)
    {
        $offset = $request->input('offset', 0);
        $limit = $request->input('limit', 100);

        try {
            $panelService = $this->panelFactory->make($server);
            $users = $panelService->getAllUsers($server, $offset, $limit);

            return response()->json([
                'success' => true,
                'users' => $users,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Sync server user count with panel
     */
    public function syncUserCount(Server $server)
    {
        try {
            $panelService = $this->panelFactory->make($server);
            $health = $panelService->getServerHealth($server);

            $actualCount = $health['total_users'] ?? $health['active_users'] ?? 0;
            
            $server->update(['active_users_count' => $actualCount]);

            return response()->json([
                'success' => true,
                'previous_count' => $server->getOriginal('active_users_count'),
                'new_count' => $actualCount,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get supported panel types
     */
    public function panelTypes()
    {
        return response()->json([
            'types' => $this->panelFactory->getSupportedTypes(),
        ]);
    }
}
