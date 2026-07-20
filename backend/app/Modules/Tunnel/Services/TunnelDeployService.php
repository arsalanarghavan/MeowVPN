<?php

namespace App\Modules\Tunnel\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;

class TunnelDeployService
{
    /** @param  array<string, mixed>  $endpoint */
    public function deploy(array $endpoint, string $configBody, string $remotePath, ?string $reloadCmd = null): array
    {
        $host = trim((string) ($endpoint['ssh_host'] ?? ''));
        if ($host === '') {
            return ['ok' => false, 'reason' => 'ssh_host_missing'];
        }
        $user = trim((string) ($endpoint['ssh_user'] ?? 'root'));
        $port = max(1, (int) ($endpoint['ssh_port'] ?? 22));
        $encoded = base64_encode($configBody);
        $cmd = sprintf(
            "echo %s | base64 -d | sudo tee %s > /dev/null",
            escapeshellarg($encoded),
            escapeshellarg($remotePath)
        );
        if ($reloadCmd !== null && $reloadCmd !== '') {
            $cmd .= ' && '.$reloadCmd;
        }

        $ssh = ['ssh', '-p', (string) $port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', $user.'@'.$host, $cmd];
        $keyPath = $this->materializeKey($endpoint);
        if ($keyPath !== null) {
            array_splice($ssh, 1, 0, ['-i', $keyPath]);
        }

        try {
            $result = Process::timeout(60)->run($ssh);
            if ($keyPath !== null) {
                @unlink($keyPath);
            }
            $ok = $result->successful();
            if ($ok && isset($endpoint['id']) && Schema::hasTable('svp_tunnel_endpoints')) {
                DB::table('svp_tunnel_endpoints')->where('id', (int) $endpoint['id'])->update([
                    'deployed_config' => $configBody,
                    'last_deploy_at' => now(),
                    'health_status' => 'deployed',
                ]);
            }

            return [
                'ok' => $ok,
                'reason' => $ok ? 'deployed' : 'ssh_failed',
                'detail' => $ok ? null : trim($result->errorOutput()),
            ];
        } catch (\Throwable $e) {
            Log::channel('svp-panel')->warning('tunnel.deploy_failed', ['err' => $e->getMessage()]);

            return ['ok' => false, 'reason' => 'ssh_exception', 'detail' => $e->getMessage()];
        }
    }

    /** @param  array<string, mixed>  $endpoint */
    protected function materializeKey(array $endpoint): ?string
    {
        $key = trim((string) ($endpoint['ssh_private_key'] ?? ''));
        if ($key === '') {
            return null;
        }
        $path = sys_get_temp_dir().'/meow-tunnel-'.md5($key).'.key';
        if (! file_exists($path)) {
            file_put_contents($path, $key);
            chmod($path, 0600);
        }

        return $path;
    }

    /** @param  object|array<string, mixed>  $endpoint */
    public function execEcho(object|array $endpoint, string $remoteCmd): array
    {
        $endpoint = is_array($endpoint) ? $endpoint : (array) $endpoint;
        $host = trim((string) ($endpoint['ssh_host'] ?? ''));
        if ($host === '') {
            return ['ok' => false, 'reason' => 'ssh_host_missing'];
        }
        $user = trim((string) ($endpoint['ssh_user'] ?? 'root'));
        $port = max(1, (int) ($endpoint['ssh_port'] ?? 22));
        $ssh = ['ssh', '-p', (string) $port, '-o', 'BatchMode=yes', '-o', 'ConnectTimeout=10', '-o', 'StrictHostKeyChecking=accept-new', $user.'@'.$host, $remoteCmd];
        $keyPath = $this->materializeKey($endpoint);
        if ($keyPath !== null) {
            array_splice($ssh, 1, 0, ['-i', $keyPath]);
        }
        try {
            $result = Process::timeout(15)->run($ssh);
            if ($keyPath !== null) {
                @unlink($keyPath);
            }

            return ['ok' => $result->successful(), 'reason' => $result->successful() ? 'ok' : 'ssh_failed'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'reason' => 'ssh_exception', 'detail' => $e->getMessage()];
        }
    }
}
