<?php

namespace App\Modules\L2tp\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class L2tpProvisionerService
{
    public function __construct(protected L2tpSshRunner $ssh) {}

    /**
     * @return array{ok:bool, username?:string, password?:string, reason?:string, detail?:string}
     */
    public function createUser(int $serverId): array
    {
        if ($serverId < 1 || ! Schema::hasTable('svp_l2tp_servers')) {
            return ['ok' => false, 'reason' => 'l2tp_server_missing'];
        }

        $srv = DB::table('svp_l2tp_servers')->where('id', $serverId)->where('active', 1)->first();
        if (! $srv) {
            return ['ok' => false, 'reason' => 'l2tp_server_missing'];
        }

        $username = $this->generateUsername();
        $password = $this->generatePassword();
        $chap = trim((string) ($srv->chap_path ?? '/etc/ppp/chap-secrets'));
        $reload = trim((string) ($srv->reload_cmd ?? 'sudo /bin/systemctl reload xl2tpd'));
        $line = $username.' L2TP-VPN '.$password.' *';
        $cmd = sprintf(
            "printf '%%s\\n' %s | sudo /usr/bin/tee -a %s && %s",
            escapeshellarg($line),
            escapeshellarg($chap),
            $reload
        );
        $res = $this->ssh->exec($srv, $cmd);
        if (! $res['ok']) {
            Log::channel('svp-panel')->warning('l2tp.create_user_failed', [
                'server_id' => $serverId,
                'username' => $username,
                'stderr' => $res['stderr'],
            ]);

            return ['ok' => false, 'reason' => 'l2tp_ssh_failed', 'detail' => $res['stderr']];
        }

        return ['ok' => true, 'username' => $username, 'password' => $password];
    }

    public function refreshUsage(object $svc): ?int
    {
        $username = trim((string) ($svc->l2tp_username ?? ''));
        $sid = (int) ($svc->l2tp_server_id ?? 0);
        if ($username === '' || $sid < 1 || ! Schema::hasTable('svp_l2tp_servers')) {
            return null;
        }
        $srv = DB::table('svp_l2tp_servers')->where('id', $sid)->first();
        if (! $srv) {
            return null;
        }
        $tpl = trim((string) ($srv->usage_cmd_template ?? ''));
        if ($tpl === '') {
            return null;
        }
        $cmd = str_replace('{username}', escapeshellarg($username), $tpl);
        $res = $this->ssh->exec($srv, $cmd);
        if (! $res['ok']) {
            Log::channel('svp-panel')->warning('l2tp.refresh_usage_failed', [
                'service_id' => (int) ($svc->id ?? 0),
                'stderr' => $res['stderr'],
            ]);

            return null;
        }
        $bytes = (int) preg_replace('/\D/', '', $res['stdout']);
        if ($bytes < 0) {
            return null;
        }
        DB::table('svp_services')->where('id', (int) $svc->id)->update([
            'used_traffic' => $bytes,
            'traffic_synced_at' => now(),
        ]);

        return $bytes;
    }

    public function deleteExpiredUser(object $svc): bool
    {
        $username = trim((string) ($svc->l2tp_username ?? ''));
        $sid = (int) ($svc->l2tp_server_id ?? 0);
        if ($username === '' || $sid < 1) {
            return false;
        }
        $srv = DB::table('svp_l2tp_servers')->where('id', $sid)->first();
        if (! $srv) {
            return false;
        }
        $chap = trim((string) ($srv->chap_path ?? '/etc/ppp/chap-secrets'));
        $reload = trim((string) ($srv->reload_cmd ?? 'sudo /bin/systemctl reload xl2tpd'));
        $pattern = '/^'.preg_quote($username, '/').'[[:space:]]/d';
        $cmd = sprintf(
            'sudo /usr/bin/sed -i %s %s && %s',
            escapeshellarg($pattern),
            escapeshellarg($chap),
            $reload
        );
        $res = $this->ssh->exec($srv, $cmd);
        if (! $res['ok']) {
            Log::channel('svp-panel')->warning('l2tp.delete_user_failed', [
                'service_id' => (int) ($svc->id ?? 0),
                'stderr' => $res['stderr'],
            ]);

            return false;
        }

        return true;
    }

    public static function isL2tp(object $svc): bool
    {
        return (string) ($svc->service_type ?? 'xray') === 'l2tp';
    }

    protected function generateUsername(): string
    {
        return 'svp'.strtolower(Str::random(8));
    }

    protected function generatePassword(): string
    {
        return Str::password(12, letters: true, numbers: true, symbols: false);
    }
}
