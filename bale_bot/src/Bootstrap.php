<?php

namespace MeowVpn\BaleBot;

use Svp\Bot\Core\Http\HttpBotBackendClient;
use Svp\Bot\Core\WebhookIngress;

final class Bootstrap
{
    public function __construct(
        protected WebhookIngress $ingress,
    ) {}

    public static function fromEnv(): self
    {
        $backendUrl = rtrim((string) getenv('BACKEND_URL'), '/');
        $secret = (string) getenv('SVP_BOT_SERVICE_SECRET');
        $webhookSecret = (string) getenv('BALE_WEBHOOK_SECRET');

        $client = new HttpBotBackendClient($backendUrl, $secret, 'bale');
        $ingress = new WebhookIngress($client, 'bale', $webhookSecret);

        return new self($ingress);
    }

    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

        if ($method === 'GET' && ($uri === '/health' || $uri === '/health/ready')) {
            $this->json(200, ['ok' => true, 'service' => 'bale_bot']);

            return;
        }

        if ($method !== 'POST') {
            $this->json(405, ['ok' => false, 'message' => 'method_not_allowed']);

            return;
        }

        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $body = (string) file_get_contents('php://input');

        if (preg_match('#^/webhook/bale/([^/]+)$#', $uri, $m)) {
            $result = $this->ingress->handle(urldecode($m[1]), $body, is_array($headers) ? $headers : []);
            $this->json((int) $result['status'], $result['body']);

            return;
        }

        if (preg_match('#^/webhook/bale/reseller/(\d+)/([^/]+)$#', $uri, $m)) {
            $result = $this->ingress->handleReseller((int) $m[1], urldecode($m[2]), $body, is_array($headers) ? $headers : []);
            $this->json((int) $result['status'], $result['body']);

            return;
        }

        $this->json(404, ['ok' => false, 'message' => 'not_found']);
    }

    /** @param  array<string, mixed>  $payload */
    protected function json(int $status, array $payload): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    }
}
