<?php

namespace App\Modules\Tunnel\Providers;

use App\Modules\Tunnel\Contracts\TunnelProviderInterface;

class XrayReverseTunnelProvider implements TunnelProviderInterface
{
    public function providerKey(): string
    {
        return 'xray_reverse';
    }

    /** @param  array<string, mixed>  $endpoint */
    public function renderConfig(array $endpoint): string
    {
        $cfg = is_array($endpoint['config_json'] ?? null) ? $endpoint['config_json'] : [];
        $bridgePort = (int) ($cfg['bridge_port'] ?? 10443);
        $central = (string) ($cfg['central_addr'] ?? '127.0.0.1:443');

        return json_encode([
            'log' => ['loglevel' => 'warning'],
            'inbounds' => [[
                'tag' => 'bridge',
                'port' => $bridgePort,
                'protocol' => 'dokodemo-door',
                'settings' => ['address' => explode(':', $central)[0], 'port' => (int) (explode(':', $central)[1] ?? 443), 'network' => 'tcp'],
            ]],
            'outbounds' => [['protocol' => 'freedom', 'tag' => 'direct']],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /** @param  array<string, mixed>  $endpoint */
    public function deployRemote(array $endpoint): array
    {
        return app(\App\Modules\Tunnel\Services\TunnelDeployService::class)->deploy($endpoint, $this->renderConfig($endpoint), '/etc/xray/bridge.json', 'sudo systemctl restart xray-bridge || true');
    }
}
