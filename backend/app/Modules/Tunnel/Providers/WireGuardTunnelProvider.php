<?php

namespace App\Modules\Tunnel\Providers;

use App\Modules\Tunnel\Contracts\TunnelProviderInterface;

class WireGuardTunnelProvider implements TunnelProviderInterface
{
    public function providerKey(): string
    {
        return 'wireguard';
    }

    /** @param  array<string, mixed>  $endpoint */
    public function renderConfig(array $endpoint): string
    {
        $cfg = is_array($endpoint['config_json'] ?? null) ? $endpoint['config_json'] : [];

        return implode("\n", [
            '[Interface]',
            'PrivateKey = '.((string) ($cfg['private_key'] ?? '')),
            'Address = '.((string) ($cfg['address'] ?? '10.66.66.2/32')),
            '',
            '[Peer]',
            'PublicKey = '.((string) ($cfg['peer_public_key'] ?? '')),
            'Endpoint = '.((string) ($cfg['peer_endpoint'] ?? '')),
            'AllowedIPs = '.((string) ($cfg['allowed_ips'] ?? '0.0.0.0/0')),
            'PersistentKeepalive = 25',
        ])."\n";
    }

    /** @param  array<string, mixed>  $endpoint */
    public function deployRemote(array $endpoint): array
    {
        return app(\App\Modules\Tunnel\Services\TunnelDeployService::class)->deploy($endpoint, $this->renderConfig($endpoint), '/etc/wireguard/meow-edge.conf', 'sudo wg-quick down meow-edge 2>/dev/null; sudo wg-quick up meow-edge');
    }
}
