<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Server;
use App\Services\ServerSelectionService;
use App\Services\VpnPanelFactory;
use App\Services\AezaApiService;

class ServerController extends Controller
{
    public function __construct(
        private ServerSelectionService $serverSelectionService,
        private VpnPanelFactory $panelFactory,
        private AezaApiService $aezaApi
    ) {}

    public function index(Request $request)
    {
        $query = Server::query();

        if ($request->has('location_tag')) {
            $query->where('location_tag', $request->location_tag);
        }

        if ($request->has('region')) {
            $query->where('region', $request->region);
        }

        if ($request->has('server_category')) {
            $query->where('server_category', $request->server_category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('panel_type')) {
            $query->where('panel_type', $request->panel_type);
        }

        return response()->json($query->get());
    }

    public function available(Request $request)
    {
        $region = $request->query('region');
        $serverCategory = $request->query('server_category');
        $locationTag = $request->query('location_tag');

        $servers = $this->serverSelectionService->getAvailableServers($locationTag, $region, $serverCategory);
        $locations = $this->serverSelectionService->getAvailableLocations($region, $serverCategory);

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
            'flag_emoji' => 'required|string|max:10',
            'ip_address' => 'required|ip',
            'api_domain' => 'required|string',
            'admin_user' => 'nullable|string', // Nullable for Hiddify
            'admin_pass' => 'nullable|string', // Nullable for Hiddify
            'api_key' => 'nullable|string', // For Hiddify
            'capacity' => 'required|integer|min:1',
            'type' => 'required|in:single,multi_relay',
            'location_tag' => 'required|string|max:10',
            'region' => 'required|in:iran,foreign',
            'server_category' => 'required|in:tunnel_entry,tunnel_exit,direct',
            'is_active' => 'boolean',
            'is_central' => 'boolean',
            'panel_type' => 'required|in:marzban,hiddify',
        ]);

        if (!$this->validateRegionCategory($data['region'], $data['server_category'])) {
            return response()->json([
                'error' => 'ترکیب منطقه و دسته نامعتبر است: ورودی تانل فقط ایران، خروجی تانل و مستقیم فقط خارج'
            ], 422);
        }

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

        if (!empty($data['is_central'])) {
            Server::query()->update(['is_central' => false]);
        }

        $server = Server::create($data);

        return response()->json($server, 201);
    }

    private function validateRegionCategory(string $region, string $serverCategory): bool
    {
        if ($serverCategory === Server::CATEGORY_TUNNEL_ENTRY) {
            return $region === Server::REGION_IRAN;
        }
        if (in_array($serverCategory, [Server::CATEGORY_TUNNEL_EXIT, Server::CATEGORY_DIRECT], true)) {
            return $region === Server::REGION_FOREIGN;
        }
        return true;
    }

    public function update(Request $request, Server $server)
    {
        $data = $request->validate([
            'name' => 'sometimes|string',
            'flag_emoji' => 'sometimes|string|max:10',
            'ip_address' => 'sometimes|ip',
            'api_domain' => 'sometimes|string',
            'admin_user' => 'nullable|string',
            'admin_pass' => 'nullable|string',
            'api_key' => 'nullable|string',
            'capacity' => 'sometimes|integer|min:1',
            'type' => 'sometimes|in:single,multi_relay',
            'location_tag' => 'sometimes|string|max:10',
            'region' => 'sometimes|in:iran,foreign',
            'server_category' => 'sometimes|in:tunnel_entry,tunnel_exit,direct',
            'is_active' => 'boolean',
            'is_central' => 'boolean',
            'panel_type' => 'sometimes|in:marzban,hiddify',
        ]);

        if (!empty($data['is_central'] ?? false)) {
            Server::where('id', '!=', $server->id)->update(['is_central' => false]);
        }

        if (isset($data['region']) || isset($data['server_category'])) {
            $region = $data['region'] ?? $server->region;
            $category = $data['server_category'] ?? $server->server_category;
            if (!$this->validateRegionCategory($region, $category)) {
                return response()->json([
                    'error' => 'ترکیب منطقه و دسته نامعتبر است: ورودی تانل فقط ایران، خروجی تانل و مستقیم فقط خارج'
                ], 422);
            }
        }

        // Do not overwrite credentials with empty string (leave blank = do not change)
        if (isset($data['admin_pass']) && $data['admin_pass'] === '') {
            unset($data['admin_pass']);
        }
        if (isset($data['api_key']) && $data['api_key'] === '') {
            unset($data['api_key']);
        }

        $server->update($data);

        // Invalidate Marzban token when admin password changed so next request uses new password
        if ($server->panel_type === 'marzban' && array_key_exists('admin_pass', $data)) {
            $this->panelFactory->marzban()->invalidateToken($server);
        }

        return response()->json($server);
    }

    /**
     * Restart panel/core (Marzban only; Hiddify returns 501).
     */
    public function restartPanel(Server $server)
    {
        $panel = $this->panelFactory->make($server);
        if (!$panel->restartPanel($server)) {
            return response()->json([
                'error' => 'ریستارت پنل برای این نوع پنل پشتیبانی نمی‌شود',
            ], 501);
        }
        return response()->json([
            'message' => 'درخواست ریستارت پنل ارسال شد',
        ]);
    }

    /**
     * Reboot VPS (AEZA only).
     */
    public function reboot(Server $server)
    {
        if ($server->provider !== 'aeza' || empty($server->aeza_server_id)) {
            return response()->json(['error' => 'این عملیات فقط برای سرورهای AEZA امکان‌پذیر است'], 400);
        }
        $result = $this->aezaApi->ctl($server->aeza_server_id, 'reboot');
        if ($result['error'] ?? true) {
            return response()->json(['error' => $result['message'] ?? 'خطا در ریبوت'], 502);
        }
        return response()->json(['message' => 'درخواست ریبوت ارسال شد']);
    }

    /**
     * Suspend VPS (AEZA only).
     */
    public function suspend(Server $server)
    {
        if ($server->provider !== 'aeza' || empty($server->aeza_server_id)) {
            return response()->json(['error' => 'این عملیات فقط برای سرورهای AEZA امکان‌پذیر است'], 400);
        }
        $result = $this->aezaApi->ctl($server->aeza_server_id, 'suspend');
        if ($result['error'] ?? true) {
            return response()->json(['error' => $result['message'] ?? 'خطا در تعلیق'], 502);
        }
        return response()->json(['message' => 'درخواست تعلیق VPS ارسال شد']);
    }

    /**
     * Resume VPS (AEZA only).
     */
    public function resume(Server $server)
    {
        if ($server->provider !== 'aeza' || empty($server->aeza_server_id)) {
            return response()->json(['error' => 'این عملیات فقط برای سرورهای AEZA امکان‌پذیر است'], 400);
        }
        $result = $this->aezaApi->ctl($server->aeza_server_id, 'resume');
        if ($result['error'] ?? true) {
            return response()->json(['error' => $result['message'] ?? 'خطا در ازسرگیری'], 502);
        }
        return response()->json(['message' => 'درخواست ازسرگیری VPS ارسال شد']);
    }

    /**
     * Reinstall VPS OS (AEZA only). Warning: panel (e.g. Marzban) will be lost.
     */
    public function reinstall(Request $request, Server $server)
    {
        if ($server->provider !== 'aeza' || empty($server->aeza_server_id)) {
            return response()->json(['error' => 'این عملیات فقط برای سرورهای AEZA امکان‌پذیر است'], 400);
        }
        $data = $request->validate([
            'os' => 'nullable|string',
            'recipe' => 'nullable|string',
            'password' => 'nullable|string',
        ]);
        $result = $this->aezaApi->reinstall($server->aeza_server_id, $data);
        if ($result['error'] ?? true) {
            return response()->json(['error' => $result['message'] ?? 'خطا در ری‌اینستال'], 502);
        }
        return response()->json(['message' => 'درخواست ری‌اینستال ارسال شد', 'response' => $result['response'] ?? []]);
    }

    /**
     * Change root password (AEZA only).
     */
    public function changeRootPassword(Request $request, Server $server)
    {
        if ($server->provider !== 'aeza' || empty($server->aeza_server_id)) {
            return response()->json(['error' => 'این عملیات فقط برای سرورهای AEZA امکان‌پذیر است'], 400);
        }
        $data = $request->validate([
            'password' => 'required|string|min:6',
        ]);
        $result = $this->aezaApi->changePassword($server->aeza_server_id, $data['password']);
        if ($result['error'] ?? true) {
            return response()->json(['error' => $result['message'] ?? 'خطا در تغییر رمز'], 502);
        }
        return response()->json(['message' => 'رمز root با موفقیت تغییر کرد']);
    }

    /**
     * Get VPS stats from AEZA (AEZA only).
     */
    public function vpsStats(Server $server)
    {
        if ($server->provider !== 'aeza' || empty($server->aeza_server_id)) {
            return response()->json(['error' => 'این عملیات فقط برای سرورهای AEZA امکان‌پذیر است'], 400);
        }
        $result = $this->aezaApi->getCharts($server->aeza_server_id);
        if ($result['error'] ?? true) {
            return response()->json(['error' => $result['message'] ?? 'خطا در دریافت آمار'], 502);
        }
        return response()->json($result['response'] ?? ['items' => []]);
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
                    'region' => $server->region ?? 'foreign',
                    'server_category' => $server->server_category ?? 'direct',
                    'is_central' => (bool) ($server->is_central ?? false),
                    'panel_type' => $server->panel_type,
                    'provider' => $server->provider ?? null,
                    'aeza_server_id' => $server->aeza_server_id ?? null,
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
                    'region' => $server->region ?? 'foreign',
                    'server_category' => $server->server_category ?? 'direct',
                    'is_central' => (bool) ($server->is_central ?? false),
                    'panel_type' => $server->panel_type,
                    'provider' => $server->provider ?? null,
                    'aeza_server_id' => $server->aeza_server_id ?? null,
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
        $offset = max(0, (int) $request->input('offset', 0));
        $limit = min((int) $request->input('limit', 100), 500);

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

    /**
     * Get region and server_category options for forms/filters
     */
    public function regionAndCategoryOptions()
    {
        return response()->json([
            'regions' => [
                ['value' => 'iran', 'label' => 'ایران'],
                ['value' => 'foreign', 'label' => 'خارج'],
            ],
            'server_categories' => [
                ['value' => 'tunnel_entry', 'label' => 'ورودی تانل'],
                ['value' => 'tunnel_exit', 'label' => 'خروجی تانل'],
                ['value' => 'direct', 'label' => 'مستقیم'],
            ],
        ]);
    }
}
