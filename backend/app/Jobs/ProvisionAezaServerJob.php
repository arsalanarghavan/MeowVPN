<?php

namespace App\Jobs;

use App\Models\AezaOrder;
use App\Services\AezaApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Poll AEZA order until server is ready, then update aeza_orders with IP and password.
 */
class ProvisionAezaServerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public string $queue = 'default';

    public int $tries = 60; // 60 * 30s = 30 min max

    public int $timeout = 35;

    public int $backoff = 30; // seconds between attempts

    public function __construct(
        public string $orderId
    ) {}

    public function handle(AezaApiService $aeza): void
    {
        $record = AezaOrder::where('order_id', $this->orderId)->first();
        if (!$record || !$record->isPending()) {
            return;
        }

        $orderResult = $aeza->getOrder($this->orderId);
        if ($orderResult['error'] ?? true) {
            $record->update([
                'status' => 'failed',
                'error_message' => $orderResult['message'] ?? 'Failed to fetch order',
            ]);
            Log::warning('AEZA getOrder failed', ['order_id' => $this->orderId, 'result' => $orderResult]);
            return;
        }

        $items = $orderResult['response']['items'] ?? [];
        $serverId = null;

        foreach ($items as $item) {
            $id = $item['id'] ?? $item['service_id'] ?? $item['server_id'] ?? null;
            $status = $item['status'] ?? $item['state'] ?? null;
            if ($id && ($status === 'active' || $status === 'running' || $status === 'ready' || $status === 1)) {
                $serverId = (string) $id;
                break;
            }
            if ($id) {
                $serverId = (string) $id;
            }
        }

        if (!$serverId) {
            $this->release($this->backoff);
            return;
        }

        $serverResult = $aeza->getServer($serverId);
        if ($serverResult['error'] ?? true) {
            $this->release($this->backoff);
            return;
        }

        $server = is_array($serverResult['response']) ? $serverResult['response'] : [];
        $ip = $server['ip'] ?? $server['ip_address'] ?? $server['address'] ?? null;
        $password = $server['password'] ?? $server['root_password'] ?? $server['admin_password'] ?? null;

        if (!$ip) {
            $this->release($this->backoff);
            return;
        }

        $record->update([
            'status' => 'ready',
            'aeza_server_id' => $serverId,
            'ip_address' => $ip,
            'root_password' => $password,
            'meta' => array_merge($record->meta ?? [], ['server' => $server]),
            'error_message' => null,
        ]);

        Log::info('AEZA order ready', ['order_id' => $this->orderId, 'server_id' => $serverId, 'ip' => $ip]);
    }

    public function failed(\Throwable $exception): void
    {
        AezaOrder::where('order_id', $this->orderId)->update([
            'status' => 'failed',
            'error_message' => $exception->getMessage(),
        ]);
        Log::error('ProvisionAezaServerJob failed: ' . $exception->getMessage(), ['order_id' => $this->orderId]);
    }
}
