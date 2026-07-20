<?php

namespace App\Modules\Tunnel\Mutations;

use App\Modules\Tunnel\Services\TunnelEndpointService;
use Illuminate\Contracts\Auth\Authenticatable;

class TunnelMutations
{
    /** @var list<string> */
    private const SAVE_FIELDS = [
        'node_id', 'label', 'provider', 'public_ip', 'ssh_host', 'ssh_port', 'ssh_user', 'ssh_auth',
        'ssh_password', 'ssh_private_key', 'ssh_key_passphrase', 'config_json', 'active', 'sort_order',
    ];

    public function __construct(protected TunnelEndpointService $endpoints) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'tunnel_add' => [self::class, 'tunnelAdd'],
            'tunnel_update' => [self::class, 'tunnelUpdate'],
            'tunnel_delete' => [self::class, 'tunnelDelete'],
            'tunnel_deploy' => [self::class, 'tunnelDeploy'],
            'tunnel_preview' => [self::class, 'tunnelPreview'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function tunnelAdd(array $payload, ?Authenticatable $actor): array
    {
        return $this->endpoints->save(collect($payload)->only(self::SAVE_FIELDS)->all());
    }

    /** @param  array<string, mixed>  $payload */
    public function tunnelUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['edit_id'] ?? 0);
        if ($id < 1) {
            return svp_err('invalid_id');
        }

        return $this->endpoints->save(collect($payload)->only(self::SAVE_FIELDS)->all(), $id);
    }

    /** @param  array<string, mixed>  $payload */
    public function tunnelDelete(array $payload, ?Authenticatable $actor): array
    {
        return $this->endpoints->delete((int) ($payload['id'] ?? 0));
    }

    /** @param  array<string, mixed>  $payload */
    public function tunnelDeploy(array $payload, ?Authenticatable $actor): array
    {
        return $this->endpoints->deploy((int) ($payload['id'] ?? 0));
    }

    /** @param  array<string, mixed>  $payload */
    public function tunnelPreview(array $payload, ?Authenticatable $actor): array
    {
        return $this->endpoints->previewConfig((int) ($payload['id'] ?? 0));
    }
}
