<?php

namespace App\Http\Controllers;

use App\Models\AezaOrder;
use App\Models\Server;
use App\Jobs\ProvisionAezaServerJob;
use App\Services\AezaApiService;
use Illuminate\Http\Request;

class AezaController extends Controller
{
    public function __construct(
        private AezaApiService $aeza
    ) {}

    /**
     * GET /api/aeza/products - List products for dropdown
     */
    public function products()
    {
        $result = $this->aeza->products(null);
        if ($result['error'] ?? true) {
            return response()->json([
                'error' => $result['message'] ?? 'Failed to fetch products',
            ], 502);
        }
        return response()->json($result['response'] ?? ['items' => []]);
    }

    /**
     * GET /api/aeza/os - List OS (for reinstall if needed)
     */
    public function os()
    {
        $result = $this->aeza->osList();
        if ($result['error'] ?? true) {
            return response()->json([
                'error' => $result['message'] ?? 'Failed to fetch OS list',
            ], 502);
        }
        return response()->json($result['response'] ?? ['items' => []]);
    }

    /**
     * POST /api/aeza/orders - Create order and dispatch polling job
     */
    public function createOrder(Request $request)
    {
        $data = $request->validate([
            'productId' => 'required',
            'term' => 'required|in:hour,month,year',
            'name' => 'required|string|max:255',
            'autoProlong' => 'boolean',
        ]);

        $result = $this->aeza->createOrder([
            'productId' => $data['productId'],
            'term' => $data['term'],
            'name' => $data['name'],
            'autoProlong' => $data['autoProlong'] ?? false,
        ]);

        if ($result['error'] ?? true) {
            return response()->json([
                'error' => $result['message'] ?? 'Failed to create order',
            ], 502);
        }

        $response = $result['response'] ?? [];
        $items = $response['items'] ?? [];
        $transaction = $response['transaction'] ?? [];
        $orderId = $transaction['id'] ?? $transaction['order_id'] ?? ($items[0]['order_id'] ?? $items[0]['id'] ?? null);

        if (!$orderId) {
            return response()->json([
                'error' => 'Order created but order ID not returned',
                'raw' => $response,
            ], 502);
        }

        $orderId = (string) $orderId;

        AezaOrder::create([
            'order_id' => $orderId,
            'status' => 'pending',
            'meta' => [
                'productId' => $data['productId'],
                'term' => $data['term'],
                'name' => $data['name'],
            ],
        ]);

        ProvisionAezaServerJob::dispatch($orderId);

        return response()->json([
            'order_id' => $orderId,
            'status' => 'pending',
            'message' => 'سفارش ثبت شد. سرور در حال آماده‌سازی است.',
        ], 201);
    }

    /**
     * GET /api/aeza/orders/{orderId} - Order status and server details when ready
     */
    public function getOrder(string $orderId)
    {
        $record = AezaOrder::where('order_id', $orderId)->first();
        if (!$record) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $payload = [
            'order_id' => $record->order_id,
            'status' => $record->status,
            'meta' => $record->meta,
        ];

        if ($record->isReady()) {
            $payload['aeza_server_id'] = $record->aeza_server_id;
            $payload['ip_address'] = $record->ip_address;
            $payload['root_password'] = $record->root_password;
            $payload['install_command'] = 'sudo bash -c "$(curl -sL https://github.com/Gozargah/Marzban-scripts/raw/master/marzban.sh)" @ install';
            $payload['install_note'] = 'پس از اتصال SSH به سرور، دستور بالا را اجرا کنید. سپس با کاربر و رمز ادمین مرزبان این سرور را در پنل ثبت کنید.';
        }

        if ($record->isFailed()) {
            $payload['error_message'] = $record->error_message;
        }

        return response()->json($payload);
    }

    /**
     * POST /api/aeza/register-server - Add AEZA-provisioned server to panel (after Marzban install)
     */
    public function registerServer(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|string',
            'name' => 'required|string|max:255',
            'flag_emoji' => 'nullable|string|max:10',
            'ip_address' => 'required|string',
            'api_domain' => 'required|string',
            'admin_user' => 'required|string',
            'admin_pass' => 'required|string',
            'capacity' => 'required|integer|min:1',
            'location_tag' => 'required|string|max:10',
            'region' => 'required|in:iran,foreign',
            'server_category' => 'required|in:tunnel_entry,tunnel_exit,direct',
        ]);

        $order = AezaOrder::where('order_id', $data['order_id'])->where('status', 'ready')->first();
        if (!$order) {
            return response()->json(['error' => 'سفارش یافت نشد یا سرور هنوز آماده نیست'], 404);
        }

        if (!Server::validateRegionCategory($data['region'], $data['server_category'])) {
            return response()->json([
                'error' => 'ترکیب منطقه و دسته نامعتبر است',
            ], 422);
        }

        $server = Server::create([
            'name' => $data['name'],
            'flag_emoji' => $data['flag_emoji'] ?? '🌐',
            'ip_address' => $data['ip_address'],
            'api_domain' => $data['api_domain'],
            'admin_user' => $data['admin_user'],
            'admin_pass' => $data['admin_pass'],
            'capacity' => $data['capacity'],
            'active_users_count' => 0,
            'type' => 'single',
            'location_tag' => $data['location_tag'],
            'region' => $data['region'],
            'server_category' => $data['server_category'],
            'is_active' => true,
            'panel_type' => 'marzban',
            'provider' => 'aeza',
            'aeza_server_id' => $order->aeza_server_id,
            'aeza_order_id' => $order->order_id,
        ]);

        return response()->json([
            'message' => 'سرور با موفقیت به پنل اضافه شد',
            'server' => $server,
        ], 201);
    }
}
