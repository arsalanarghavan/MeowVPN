<?php

namespace App\Modules\Tunnel\Providers;

use App\Modules\Tunnel\Contracts\TunnelProviderInterface;

class GostTunnelProvider implements TunnelProviderInterface
{
    public function providerKey(): string
    {
        return 'gost';
    }

    /** @param  array<string, mixed>  $endpoint */
    public function renderConfig(array $endpoint): string
    {
        $cfg = is_array($endpoint['config_json'] ?? null) ? $endpoint['config_json'] : [];
        $listen = (string) ($cfg['listen'] ?? ':8443');
        $forward = (string) ($cfg['forward'] ?? '127.0.0.1:443');

        return "-L=tcp://{$listen}/{$forward}\n";
    }

    /** @param  array<string, mixed>  $endpoint */
    public function deployRemote(array $endpoint): array
    {
        $cfg = is_array($endpoint['config_json'] ?? null) ? $endpoint['config_json'] : [];
        $unit = "[Unit]\nDescription=MeowVPN Gost Tunnel\nAfter=network.target\n\n[Service]\nExecStart=/usr/local/bin/gost ".trim($this->renderConfig($endpoint))."\nRestart=always\n\n[Install]\nWantedBy=multi-user.target\n";

        return app(\App\Modules\Tunnel\Services\TunnelDeployService::class)->deploy($endpoint, $unit, '/etc/systemd/system/meow-gost.service', 'sudo systemctl daemon-reload && sudo systemctl enable --now meow-gost');
    }
}
