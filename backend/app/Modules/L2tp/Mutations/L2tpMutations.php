<?php

namespace App\Modules\L2tp\Mutations;

use App\Modules\L2tp\Services\L2tpServerService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class L2tpMutations
{
    /** @var list<string> */
    private const SAVE_FIELDS = [
        'label', 'ssh_host', 'ssh_port', 'ssh_user', 'ssh_auth', 'ssh_password',
        'ssh_private_key', 'ssh_key_passphrase', 'l2tp_host', 'l2tp_psk', 'chap_path',
        'reload_cmd', 'usage_cmd_template', 'apps_note', 'active',
    ];

    public function __construct(protected L2tpServerService $servers) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'l2tp_add' => [self::class, 'l2tpAdd'],
            'l2tp_update' => [self::class, 'l2tpUpdate'],
            'l2tp_delete' => [self::class, 'l2tpDelete'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function l2tpAdd(array $payload, ?Authenticatable $actor): array
    {
        return $this->servers->save(collect($payload)->only(self::SAVE_FIELDS)->all());
    }

    /** @param  array<string, mixed>  $payload */
    public function l2tpUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['edit_id'] ?? 0);
        if ($id < 1) {
            return svp_err('invalid_id');
        }

        return $this->servers->save(collect($payload)->only(self::SAVE_FIELDS)->all(), $id);
    }

    /** @param  array<string, mixed>  $payload */
    public function l2tpDelete(array $payload, ?Authenticatable $actor): array
    {
        DB::table('svp_l2tp_servers')->where('id', (int) ($payload['id'] ?? 0))->delete();

        return svp_ok();
    }
}
