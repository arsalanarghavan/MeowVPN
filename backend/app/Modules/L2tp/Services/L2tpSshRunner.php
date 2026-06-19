<?php

namespace App\Modules\L2tp\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class L2tpSshRunner
{
    /** @return array{ok: bool, stdout: string, stderr: string} */
    public function exec(object $serverRow, string $remoteCommand): array
    {
        $host = trim((string) ($serverRow->ssh_host ?? ''));
        $user = trim((string) ($serverRow->ssh_user ?? 'svpbot'));
        $port = max(1, (int) ($serverRow->ssh_port ?? 22));
        if ($host === '') {
            return ['ok' => false, 'stdout' => '', 'stderr' => 'missing_host'];
        }

        $cmd = ['ssh', '-p', (string) $port, '-o', 'BatchMode=yes', '-o', 'StrictHostKeyChecking=accept-new', $user.'@'.$host, $remoteCommand];

        $keyPath = $this->materializeKey($serverRow);
        if ($keyPath !== null) {
            array_splice($cmd, 1, 0, ['-i', $keyPath]);
        }

        try {
            $result = Process::timeout(30)->run($cmd);
            if ($keyPath !== null) {
                @unlink($keyPath);
            }

            return [
                'ok' => $result->successful(),
                'stdout' => trim($result->output()),
                'stderr' => trim($result->errorOutput()),
            ];
        } catch (\Throwable $e) {
            if ($keyPath !== null) {
                @unlink($keyPath);
            }
            Log::channel('svp-panel')->warning('l2tp.ssh_failed', ['err' => $e->getMessage()]);

            return ['ok' => false, 'stdout' => '', 'stderr' => $e->getMessage()];
        }
    }

    protected function materializeKey(object $serverRow): ?string
    {
        $enc = trim((string) ($serverRow->ssh_private_key_enc ?? ''));
        if ($enc === '') {
            return null;
        }
        try {
            $plain = Crypt::decryptString($enc);
        } catch (\Throwable) {
            return null;
        }
        $path = sys_get_temp_dir().'/svp-l2tp-'.md5($plain).'.key';
        if (! file_exists($path)) {
            file_put_contents($path, $plain);
            chmod($path, 0600);
        }

        return $path;
    }
}
