<?php

namespace App\Modules\XrayCore\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class NodeAgentClient
{
    public function __construct(protected LocalNodeService $localNode) {}

    /**
     * @param  array<string, mixed>  $config
     * @return array{ok:bool, reason?:string, detail?:string}
     */
    public function applyConfig(?object $node, array $config): array
    {
        return $this->request($this->resolveNode($node), 'POST', '/apply', ['config' => $config]);
    }

    /** @return array<string, mixed> */
    public function health(?object $node = null): array
    {
        return $this->request($this->resolveNode($node), 'GET', '/health');
    }

    /** @return array<string, mixed> */
    public function restart(?object $node = null): array
    {
        return $this->request($this->resolveNode($node), 'POST', '/restart');
    }

    /** @return array<string, mixed> */
    public function stats(?object $node = null): array
    {
        return $this->request($this->resolveNode($node), 'GET', '/stats');
    }

    protected function resolveNode(?object $node): object
    {
        if ($node !== null) {
            return $node;
        }
        $local = $this->localNode->localNode();
        if ($local !== null) {
            return $local;
        }

        return (object) ['agent_url' => $this->localNode->agentUrl()];
    }

    /**
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    protected function request(object $node, string $method, string $path, array $body = []): array
    {
        $base = rtrim(trim((string) ($node->agent_url ?? '')), '/');
        if ($base === '') {
            $base = $this->localNode->agentUrl();
        }
        $url = $base.$path;

        try {
            $http = Http::timeout(30)->acceptJson();
            if (str_starts_with($base, 'https://')) {
                $http = $http->withOptions(['verify' => false]);
            }
            $http = $this->applyTls($http, $node);
            $response = match (strtoupper($method)) {
                'GET' => $http->get($url),
                'POST' => $http->post($url, $body),
                default => $http->send($method, $url, ['json' => $body]),
            };
            if (! $response->successful()) {
                return [
                    'ok' => false,
                    'reason' => 'agent_http_error',
                    'detail' => $response->body(),
                ];
            }
            $json = $response->json();

            return is_array($json) ? $json : ['ok' => false, 'reason' => 'agent_invalid_json'];
        } catch (\Throwable $e) {
            Log::channel('svp-panel')->warning('xray.agent_request_failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return ['ok' => false, 'reason' => 'agent_unreachable', 'detail' => $e->getMessage()];
        }
    }

    protected function applyTls(\Illuminate\Http\Client\PendingRequest $http, object $node): \Illuminate\Http\Client\PendingRequest
    {
        $cert = trim((string) ($node->agent_client_cert ?? ''));
        $key = trim((string) ($node->agent_client_key ?? ''));
        $ca = trim((string) ($node->agent_ca_cert ?? ''));
        if ($cert !== '' && $key !== '') {
            $certPath = $this->writeTempPem('cert', $cert);
            $keyPath = $this->writeTempPem('key', $key);
            $http = $http->withOptions([
                'cert' => [$certPath, ''],
                'ssl_key' => [$keyPath, ''],
            ]);
        }
        if ($ca !== '') {
            $caPath = $this->writeTempPem('ca', $ca);
            $http = $http->withOptions(['verify' => $caPath]);
        }

        return $http;
    }

    protected function writeTempPem(string $kind, string $pem): string
    {
        $path = sys_get_temp_dir().'/meow-agent-'.$kind.'-'.md5($pem).'.pem';
        if (! file_exists($path)) {
            file_put_contents($path, $pem);
        }

        return $path;
    }
}
