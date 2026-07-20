<?php

namespace App\Modules\XrayCore\Services;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\XrayCore\DTO\ProvisionResult;
use App\Modules\XrayCore\DTO\TrafficStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NativeXrayDriver implements PanelDriverInterface
{
    public function __construct(
        protected NativeXrayProvisioner $provisioner,
        protected SubscriptionUriBuilder $uris,
        protected TrafficSyncService $traffic,
        protected XrayConfigBuilder $configBuilder,
        protected NodeAgentClient $agent,
    ) {}

    public function driverType(): string
    {
        return 'native';
    }

    public function provisionFromPlan(int $userId, SvpPlan $plan, ?int $volumeGb, ?string $platform): ProvisionResult
    {
        return $this->provisioner->provisionFromPlan($userId, $plan, $volumeGb, $platform);
    }

    /** @param  array<string, mixed>|object  $svc */
    public function addClientFromServiceRow(array|object $svc): array
    {
        $push = $this->provisioner->pushLocalConfig();
        if (empty($push['ok'])) {
            return ['ok' => false, 'reason' => 'native_config_push_failed', 'detail' => $push['detail'] ?? null];
        }

        return ['ok' => true, 'action' => 'synced'];
    }

    /** @param  object  $svc */
    public function buildSubscriptionUris(object $svc): array
    {
        return $this->uris->buildForService($svc);
    }

    /** @param  object  $svc */
    public function syncTraffic(object $svc): ?TrafficStats
    {
        return $this->traffic->syncOne($svc);
    }

    /** @param  object  $svc */
    public function disableClient(object $svc): bool
    {
        DB::table('svp_services')->where('id', (int) ($svc->id ?? 0))->update(['client_enabled' => 0]);
        $push = $this->provisioner->pushLocalConfig();

        return ! empty($push['ok']);
    }

    /** @param  object  $svc */
    public function deleteClient(object $svc): bool
    {
        DB::table('svp_services')->where('id', (int) ($svc->id ?? 0))->update(['deleted_at' => now()]);
        $push = $this->provisioner->pushLocalConfig();

        return ! empty($push['ok']);
    }

    /** @param  object  $svc */
    public function renewClient(object $svc, int $days, int $gb): bool
    {
        $updates = [];
        if ($days > 0) {
            $base = ! empty($svc->expires_at) ? strtotime((string) $svc->expires_at.' UTC') : time();
            $updates['expires_at'] = date('Y-m-d H:i:s', max(time(), $base) + $days * 86400);
        }
        if ($gb > 0) {
            $updates['total_traffic'] = (int) ($svc->total_traffic ?? 0) + $gb * 1073741824;
        }
        if ($updates === []) {
            return true;
        }
        DB::table('svp_services')->where('id', (int) ($svc->id ?? 0))->update($updates);
        $push = $this->provisioner->pushLocalConfig();

        return ! empty($push['ok']);
    }
}
