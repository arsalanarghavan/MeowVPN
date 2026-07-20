<?php

namespace App\Modules\Tunnel\Providers;

use App\Modules\Tunnel\Contracts\TunnelProviderInterface;

class FrpTunnelProvider implements TunnelProviderInterface
{
    public function providerKey(): string
    {
        return 'frp';
    }

    /** @param  array<string, mixed>  $endpoint */
    public function renderConfig(array $endpoint): string
    {
        $cfg = is_array($endpoint['config_json'] ?? null) ? $endpoint['config_json'] : [];
        $server = (string) ($cfg['frps_addr'] ?? '127.0.0.1');
        $port = (int) ($cfg['frps_port'] ?? 7000);
        $token = (string) ($cfg['token'] ?? '');
        $proxies = is_array($cfg['proxies'] ?? null) ? $cfg['proxies'] : [];
        $lines = [
            '[common]',
            'server_addr = '.$server,
            'server_port = '.$port,
        ];
        if ($token !== '') {
            $lines[] = 'token = '.$token;
        }
        foreach ($proxies as $i => $proxy) {
            if (! is_array($proxy)) {
                continue;
            }
            $name = (string) ($proxy['name'] ?? 'proxy_'.$i);
            $lines[] = '';
            $lines[] = '['.$name.']';
            $lines[] = 'type = '.((string) ($proxy['type'] ?? 'tcp'));
            $lines[] = 'local_ip = '.((string) ($proxy['local_ip'] ?? '127.0.0.1'));
            $lines[] = 'local_port = '.((int) ($proxy['local_port'] ?? 443));
            $lines[] = 'remote_port = '.((int) ($proxy['remote_port'] ?? 8443));
        }

        return implode("\n", $lines)."\n";
    }

    /** @param  array<string, mixed>  $endpoint */
    public function deployRemote(array $endpoint): array
    {
        return app(\App\Modules\Tunnel\Services\TunnelDeployService::class)->deploy($endpoint, $this->renderConfig($endpoint), '/etc/frp/frpc.ini');
    }
}
