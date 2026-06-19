<?php

namespace App\Modules\L2tp\Services;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class L2tpServerService
{
    /** @param  array<string, mixed>  $data */
    public function save(array $data, ?int $id = null): array
    {
        $label = trim((string) ($data['label'] ?? ''));
        $sshHost = trim((string) ($data['ssh_host'] ?? ''));
        $l2tpHost = trim((string) ($data['l2tp_host'] ?? ''));
        if ($label === '' || $sshHost === '' || $l2tpHost === '') {
            return svp_err('missing_fields');
        }

        $row = [
            'label' => $label,
            'ssh_host' => $sshHost,
            'ssh_port' => max(1, (int) ($data['ssh_port'] ?? 22)),
            'ssh_user' => trim((string) ($data['ssh_user'] ?? 'svpbot')),
            'ssh_auth' => ($data['ssh_auth'] ?? 'key') === 'password' ? 'password' : 'key',
            'l2tp_host' => $l2tpHost,
            'chap_path' => trim((string) ($data['chap_path'] ?? '/etc/ppp/chap-secrets')),
            'reload_cmd' => trim((string) ($data['reload_cmd'] ?? 'sudo /bin/systemctl reload xl2tpd')),
            'usage_cmd_template' => (string) ($data['usage_cmd_template'] ?? ''),
            'apps_note' => (string) ($data['apps_note'] ?? ''),
            'active' => ! empty($data['active']) ? 1 : 0,
        ];

        foreach ([
            'ssh_password' => 'ssh_password_enc',
            'ssh_private_key' => 'ssh_private_key_enc',
            'ssh_key_passphrase' => 'ssh_key_passphrase_enc',
            'l2tp_psk' => 'l2tp_psk_enc',
        ] as $plain => $col) {
            if (! array_key_exists($plain, $data)) {
                continue;
            }
            $val = trim((string) $data[$plain]);
            if ($val !== '') {
                $row[$col] = Crypt::encryptString($val);
            }
        }

        if ($id > 0) {
            DB::table('svp_l2tp_servers')->where('id', $id)->update($row);

            return svp_ok(['id' => $id]);
        }

        $newId = (int) DB::table('svp_l2tp_servers')->insertGetId(array_merge($row, [
            'created_at' => now(),
        ]));

        return svp_ok(['id' => $newId]);
    }

    /** @return array<string, mixed> */
    public function toAdminPayload(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'label' => (string) ($row->label ?? ''),
            'ssh_host' => (string) ($row->ssh_host ?? ''),
            'ssh_port' => (int) ($row->ssh_port ?? 22),
            'ssh_user' => (string) ($row->ssh_user ?? ''),
            'ssh_auth' => (string) ($row->ssh_auth ?? 'key'),
            'ssh_password_enc' => $this->secretPlaceholder($row->ssh_password_enc ?? null),
            'ssh_private_key_enc' => $this->secretPlaceholder($row->ssh_private_key_enc ?? null),
            'ssh_key_passphrase_enc' => $this->secretPlaceholder($row->ssh_key_passphrase_enc ?? null),
            'l2tp_host' => (string) ($row->l2tp_host ?? ''),
            'l2tp_psk_enc' => $this->secretPlaceholder($row->l2tp_psk_enc ?? null),
            'chap_path' => (string) ($row->chap_path ?? '/etc/ppp/chap-secrets'),
            'reload_cmd' => (string) ($row->reload_cmd ?? ''),
            'usage_cmd_template' => (string) ($row->usage_cmd_template ?? ''),
            'apps_note' => (string) ($row->apps_note ?? ''),
            'active' => (bool) ($row->active ?? true),
            'created_at' => $row->created_at ?? null,
        ];
    }

    protected function secretPlaceholder(mixed $stored): string
    {
        $s = trim((string) $stored);

        return $s !== '' ? '1' : '';
    }
}
