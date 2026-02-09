<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Laravel client for AEZA.NET API (https://core.aeza.net/api/).
 * Replaces Node.js aeza-net-sdk with PHP/Guzzle-style HTTP calls.
 */
class AezaApiService
{
    private string $baseUrl = 'https://core.aeza.net/api';

    public function __construct(
        private ?string $apiKey = null
    ) {
        $this->apiKey = $apiKey ?? config('services.aeza.api_key');
    }

    private function request(string $method, string $route, array $data = []): array
    {
        $url = $this->baseUrl . '/' . ltrim($route, '/');

        $request = Http::withHeaders([
            'X-API-Key' => $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30);

        $response = match ($method) {
            'GET' => $request->get($url, $data),
            'POST' => $request->post($url, $data),
            'DELETE' => $request->delete($url),
            default => $request->get($url, $data),
        };

        $body = $response->json();

        if ($response->failed()) {
            Log::warning('AEZA API error', [
                'method' => $method,
                'route' => $route,
                'status' => $response->status(),
                'body' => $body,
            ]);
            return [
                'error' => true,
                'slug' => $body['slug'] ?? 'unknown',
                'message' => $body['message'] ?? $response->body(),
                'data' => $body['data'] ?? [],
            ];
        }

        if (isset($body['error']) && $body['error']) {
            return [
                'error' => true,
                'slug' => $body['slug'] ?? 'unknown',
                'message' => $body['message'] ?? 'Unknown error',
                'data' => $body['data'] ?? [],
            ];
        }

        return ['error' => false, 'data' => $body];
    }

    /**
     * GET os list
     */
    public function osList(): array
    {
        $result = $this->request('GET', 'os');
        if ($result['error']) {
            return $result;
        }
        return [
            'error' => false,
            'response' => [
                'items' => $result['data']['items'] ?? [],
            ],
        ];
    }

    /**
     * GET products (optionally by id)
     */
    public function products(?string $id = null): array
    {
        $route = 'services/products' . ($id ? '/' . $id : '');
        $result = $this->request('GET', $route);
        if ($result['error']) {
            return $result;
        }
        $items = $result['data']['items'] ?? [];
        return [
            'error' => false,
            'response' => [
                'items' => $items,
            ],
        ];
    }

    /**
     * Create order (create server)
     * Params: productId, term (hour/month/year), name, autoProlong (bool), parameters (optional)
     */
    public function createOrder(array $params): array
    {
        $body = array_merge([
            'count' => 1,
            'method' => 'balance',
            'parameters' => [
                'recipe' => null,
                'os' => 940,
                'isoUrl' => '',
            ],
        ], $params);

        if (isset($params['parameters']) && is_array($params['parameters'])) {
            $body['parameters'] = array_merge($body['parameters'], $params['parameters']);
        }

        $result = $this->request('POST', 'services/orders', $body);
        if ($result['error']) {
            return $result;
        }
        $data = $result['data'];
        return [
            'error' => false,
            'total' => count($data['items'] ?? []),
            'response' => [
                'items' => $data['items'] ?? [],
                'transaction' => $data['transaction'] ?? null,
            ],
        ];
    }

    /**
     * Get order status by orderId
     */
    public function getOrder(string $orderId): array
    {
        $result = $this->request('GET', 'services/orders/' . $orderId);
        if ($result['error']) {
            return $result;
        }
        $data = $result['data'];
        return [
            'error' => false,
            'total' => count($data['items'] ?? []),
            'response' => [
                'items' => $data['items'] ?? [],
                'transaction' => $data['transaction'] ?? null,
            ],
        ];
    }

    /**
     * GET server(s) - list all or single by id
     */
    public function getServer(?string $serverId = null): array
    {
        $route = 'services' . ($serverId ? '/' . $serverId : '');
        $result = $this->request('GET', $route);
        if ($result['error']) {
            return $result;
        }
        $data = $result['data'];
        $total = $data['total'] ?? 1;
        $items = $data['items'] ?? [];
        return [
            'error' => false,
            'total' => $total,
            'response' => $total > 1 ? $items : ($items[0] ?? $items),
        ];
    }

    /**
     * Change root password on server
     */
    public function changePassword(string $serverId, string $password): array
    {
        $result = $this->request('POST', 'services/' . $serverId . '/changePassword', [
            'password' => $password,
        ]);
        if ($result['error']) {
            return $result;
        }
        return [
            'error' => false,
            'response' => $result['data'],
        ];
    }

    /**
     * Reinstall server (os, recipe, password)
     */
    public function reinstall(string $serverId, array $options): array
    {
        $body = array_filter([
            'os' => $options['os'] ?? null,
            'recipe' => $options['recipe'] ?? null,
            'password' => $options['password'] ?? null,
        ], fn ($v) => $v !== null);

        $result = $this->request('POST', 'services/' . $serverId . '/reinstall', $body);
        if ($result['error']) {
            return $result;
        }
        $data = $result['data'];
        return [
            'error' => false,
            'response' => [
                'status' => $data['status'] ?? 'error',
                'items' => $data['items'] ?? [],
            ],
        ];
    }

    /**
     * Delete server
     */
    public function deleteServer(string $serverId): array
    {
        $result = $this->request('DELETE', 'services/' . $serverId);
        if ($result['error']) {
            return $result;
        }
        return [
            'error' => false,
            'response' => $result['data'],
        ];
    }

    /**
     * Server control: resume, suspend, reboot
     */
    public function ctl(string $serverId, string $action): array
    {
        $result = $this->request('POST', 'services/' . $serverId . '/ctl', [
            'action' => $action,
        ]);
        if ($result['error']) {
            return $result;
        }
        return [
            'error' => false,
            'response' => ['items' => $result['data']['items'] ?? []],
        ];
    }

    /**
     * Get server charts/stats (VPS monitoring from AEZA).
     */
    public function getCharts(string $serverId): array
    {
        $result = $this->request('POST', 'services/' . $serverId . '/charts', []);
        if ($result['error']) {
            return $result;
        }
        return [
            'error' => false,
            'response' => ['items' => $result['data']['items'] ?? []],
        ];
    }
}
