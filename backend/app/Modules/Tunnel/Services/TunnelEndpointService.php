<?php

namespace App\Modules\Tunnel\Services;

use App\Modules\Tunnel\Providers\FrpTunnelProvider;
use App\Modules\Tunnel\Providers\GostTunnelProvider;
use App\Modules\Tunnel\Providers\WireGuardTunnelProvider;
use App\Modules\Tunnel\Providers\XrayReverseTunnelProvider;
use App\Modules\XrayCore\Services\LocalNodeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TunnelEndpointService
{
    /** @var array<string, object> */
    protected array $providers;

    public function __construct(protected LocalNodeService $localNode)
    {
        $this->providers = [
            'frp' => new FrpTunnelProvider,
            'gost' => new GostTunnelProvider,
            'xray_reverse' => new XrayReverseTunnelProvider,
            'wireguard' => new WireGuardTunnelProvider,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public function save(array $data, int $id = 0): array
    {
        if (! Schema::hasTable('svp_tunnel_endpoints')) {
            return svp_err('tunnel_tables_missing');
        }
        $nodeId = $this->localNode->localNodeId();
        $cfg = $data['config_json'] ?? null;
        if (is_array($cfg)) {
            $cfg = $this->injectCentralHost($cfg);
            $cfg = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $row = [
            'node_id' => $nodeId,
            'label' => trim((string) ($data['label'] ?? '')),
            'provider' => strtolower(trim((string) ($data['provider'] ?? 'frp'))),
            'public_ip' => trim((string) ($data['public_ip'] ?? '')),
            'ssh_host' => trim((string) ($data['ssh_host'] ?? '')),
            'ssh_port' => max(1, (int) ($data['ssh_port'] ?? 22)),
            'ssh_user' => trim((string) ($data['ssh_user'] ?? 'root')),
            'ssh_auth' => (string) ($data['ssh_auth'] ?? 'key'),
            'ssh_password' => $data['ssh_password'] ?? null,
            'ssh_private_key' => $data['ssh_private_key'] ?? null,
            'ssh_key_passphrase' => $data['ssh_key_passphrase'] ?? null,
            'config_json' => $cfg,
            'active' => ! isset($data['active']) || ! empty($data['active']) ? 1 : 0,
            'sort_order' => max(0, (int) ($data['sort_order'] ?? 0)),
            'updated_at' => now(),
        ];
        if ($id > 0) {
            DB::table('svp_tunnel_endpoints')->where('id', $id)->update($row);

            return svp_ok(['id' => $id]);
        }
        $row['created_at'] = now();
        $newId = (int) DB::table('svp_tunnel_endpoints')->insertGetId($row);

        return svp_ok(['id' => $newId]);
    }

    public function delete(int $id): array
    {
        DB::table('svp_tunnel_endpoints')->where('id', $id)->delete();

        return svp_ok();
    }

    public function deploy(int $id): array
    {
        $row = DB::table('svp_tunnel_endpoints')->where('id', $id)->first();
        if (! $row) {
            return svp_err('not_found');
        }
        $provider = $this->providers[(string) $row->provider] ?? null;
        if ($provider === null) {
            return svp_err('unknown_provider');
        }
        $endpoint = $this->endpointArray($row);
        $result = $provider->deployRemote($endpoint);
        if (! empty($result['ok'])) {
            $this->autoCreateHosts($row, $endpoint);
        }

        return $result;
    }

    public function previewConfig(int $id): array
    {
        $row = DB::table('svp_tunnel_endpoints')->where('id', $id)->first();
        if (! $row) {
            return svp_err('not_found');
        }
        $provider = $this->providers[(string) $row->provider] ?? null;
        if ($provider === null) {
            return svp_err('unknown_provider');
        }

        return svp_ok(['config' => $provider->renderConfig($this->endpointArray($row))]);
    }

    /** @return array{checked:int, ok:int, failed:int} */
    public function healthCheckAll(): array
    {
        if (! Schema::hasTable('svp_tunnel_endpoints')) {
            return ['checked' => 0, 'ok' => 0, 'failed' => 0];
        }
        $deploy = app(TunnelDeployService::class);
        $stats = ['checked' => 0, 'ok' => 0, 'failed' => 0];
        $rows = DB::table('svp_tunnel_endpoints')->where('active', 1)->get();
        foreach ($rows as $row) {
            $stats['checked']++;
            $host = trim((string) ($row->ssh_host ?? ''));
            if ($host === '') {
                DB::table('svp_tunnel_endpoints')->where('id', (int) $row->id)->update(['health_status' => 'no_ssh']);
                $stats['failed']++;
                continue;
            }
            $res = $deploy->execEcho($row, 'echo ok');
            $ok = ! empty($res['ok']);
            DB::table('svp_tunnel_endpoints')->where('id', (int) $row->id)->update([
                'health_status' => $ok ? 'ok' : 'ssh_fail',
            ]);
            if ($ok) {
                $stats['ok']++;
            } else {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    protected function endpointArray(object $row): array
    {
        $endpoint = (array) $row;
        if (! empty($row->config_json)) {
            $decoded = json_decode((string) $row->config_json, true);
            $endpoint['config_json'] = is_array($decoded) ? $this->injectCentralHost($decoded) : [];
        } else {
            $endpoint['config_json'] = [];
        }

        return $endpoint;
    }

    /** @param  array<string, mixed>  $cfg */
    protected function injectCentralHost(array $cfg): array
    {
        $central = $this->localNode->publicIp();
        if ($central !== '') {
            $cfg['central_host'] = $central;
            if (! isset($cfg['frps_addr']) || trim((string) $cfg['frps_addr']) === '') {
                $cfg['frps_addr'] = $central;
            }
        }

        return $cfg;
    }

    protected function autoCreateHosts(object $row, array $endpoint): void
    {
        if (! Schema::hasTable('svp_xray_hosts') || ! Schema::hasTable('svp_xray_inbounds')) {
            return;
        }
        $edgeIp = trim((string) ($row->public_ip ?? ''));
        if ($edgeIp === '') {
            $edgeIp = trim((string) ($row->ssh_host ?? ''));
        }
        if ($edgeIp === '') {
            return;
        }
        $nodeId = $this->localNode->localNodeId();
        $inbounds = DB::table('svp_xray_inbounds')
            ->where('node_id', $nodeId)
            ->where('active', 1)
            ->get();
        foreach ($inbounds as $inbound) {
            $exists = DB::table('svp_xray_hosts')
                ->where('inbound_id', (int) $inbound->id)
                ->where('address', $edgeIp)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('svp_xray_hosts')->insert([
                'inbound_id' => (int) $inbound->id,
                'remark' => 'Edge: '.trim((string) ($row->label ?? $edgeIp)),
                'address' => $edgeIp,
                'port' => 0,
                'priority' => 10,
                'active' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
