<?php

namespace App\Modules\XrayCore\Mutations;

use App\Modules\XrayCore\Services\InboundAdminService;
use App\Modules\XrayCore\Services\NativeXrayProvisioner;
use Illuminate\Contracts\Auth\Authenticatable;

class XrayCoreMutations
{
    /** @var list<string> */
    private const LOCAL_FIELDS = ['public_ip', 'base_config_json', 'label'];

    /** @var list<string> */
    private const INBOUND_FIELDS = [
        'tag', 'remark', 'protocol', 'port', 'settings_json', 'settings',
        'stream_settings_json', 'stream_settings', 'sniffing_json', 'sniffing', 'active', 'sort_order',
    ];

    /** @var list<string> */
    private const HOST_FIELDS = [
        'inbound_id', 'remark', 'address', 'port', 'sni', 'host', 'path', 'fingerprint',
        'allow_insecure', 'priority', 'active',
    ];

    public function __construct(
        protected InboundAdminService $admin,
        protected NativeXrayProvisioner $provisioner,
    ) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'vpn_server_update' => [self::class, 'vpnServerUpdate'],
            'vpn_server_health' => [self::class, 'vpnServerHealth'],
            'vpn_server_restart' => [self::class, 'vpnServerRestart'],
            'vpn_server_apply' => [self::class, 'vpnServerApply'],
            'vpn_server_overview' => [self::class, 'vpnServerOverview'],
            'xray_inbound_add' => [self::class, 'xrayInboundAdd'],
            'xray_inbound_update' => [self::class, 'xrayInboundUpdate'],
            'xray_inbound_delete' => [self::class, 'xrayInboundDelete'],
            'xray_host_add' => [self::class, 'xrayHostAdd'],
            'xray_host_update' => [self::class, 'xrayHostUpdate'],
            'xray_host_delete' => [self::class, 'xrayHostDelete'],
            // legacy aliases
            'xray_node_update' => [self::class, 'vpnServerUpdate'],
            'xray_node_health' => [self::class, 'vpnServerHealth'],
            'xray_node_restart' => [self::class, 'vpnServerRestart'],
            'xray_node_apply' => [self::class, 'vpnServerApply'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function vpnServerUpdate(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->updateLocalSettings(collect($payload)->only(self::LOCAL_FIELDS)->all());
    }

    /** @param  array<string, mixed>  $payload */
    public function vpnServerHealth(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->localHealth();
    }

    /** @param  array<string, mixed>  $payload */
    public function vpnServerRestart(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->restartLocal();
    }

    /** @param  array<string, mixed>  $payload */
    public function vpnServerApply(array $payload, ?Authenticatable $actor): array
    {
        return $this->provisioner->pushLocalConfig();
    }

    /** @param  array<string, mixed>  $payload */
    public function vpnServerOverview(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->localOverview();
    }

    /** @param  array<string, mixed>  $payload */
    public function xrayInboundAdd(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->saveInbound(collect($payload)->only(self::INBOUND_FIELDS)->all());
    }

    /** @param  array<string, mixed>  $payload */
    public function xrayInboundUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['edit_id'] ?? 0);
        if ($id < 1) {
            return svp_err('invalid_id');
        }

        return $this->admin->saveInbound(collect($payload)->only(self::INBOUND_FIELDS)->all(), $id);
    }

    /** @param  array<string, mixed>  $payload */
    public function xrayInboundDelete(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->deleteInbound((int) ($payload['id'] ?? 0));
    }

    /** @param  array<string, mixed>  $payload */
    public function xrayHostAdd(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->saveHost(collect($payload)->only(self::HOST_FIELDS)->all());
    }

    /** @param  array<string, mixed>  $payload */
    public function xrayHostUpdate(array $payload, ?Authenticatable $actor): array
    {
        $id = (int) ($payload['id'] ?? $payload['edit_id'] ?? 0);
        if ($id < 1) {
            return svp_err('invalid_id');
        }

        return $this->admin->saveHost(collect($payload)->only(self::HOST_FIELDS)->all(), $id);
    }

    /** @param  array<string, mixed>  $payload */
    public function xrayHostDelete(array $payload, ?Authenticatable $actor): array
    {
        return $this->admin->deleteHost((int) ($payload['id'] ?? 0));
    }
}
