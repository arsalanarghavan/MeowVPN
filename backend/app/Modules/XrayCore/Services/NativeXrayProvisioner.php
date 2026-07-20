<?php

namespace App\Modules\XrayCore\Services;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\XrayCore\DTO\ProvisionResult;
use App\Support\Xui\InboundTraffic;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NativeXrayProvisioner
{
    public function __construct(
        protected XrayConfigBuilder $configBuilder,
        protected NodeAgentClient $agent,
        protected LocalNodeService $localNode,
    ) {}

    public function provisionFromPlan(int $userId, SvpPlan $plan, ?int $volumeGb, ?string $platform): ProvisionResult
    {
        if (! Schema::hasTable('svp_xray_inbounds')) {
            return new ProvisionResult(ok: false, reason: 'xray_tables_missing');
        }

        $nodeId = $this->localNode->localNodeId();
        if ($nodeId < 1) {
            return new ProvisionResult(ok: false, reason: 'local_node_missing');
        }

        // Resolve inline to avoid DI cycle: PanelDriverResolver → NativeXrayDriver → this.
        $inboundRef = $this->resolveInboundRef($plan);
        if ($inboundRef < 1) {
            return new ProvisionResult(ok: false, reason: 'inbound_missing');
        }

        $inbound = DB::table('svp_xray_inbounds')->where('id', $inboundRef)->where('active', 1)->first();
        if (! $inbound) {
            return new ProvisionResult(ok: false, reason: 'inbound_not_found', detail: 'id='.$inboundRef);
        }

        $uuid = XrayConfigBuilder::generateUuid();
        $user = DB::table('svp_users')->where('id', $userId)->first();
        $canonical = ServiceNaming::provisionCanonicalLabel($user, $platform, 1);
        $email = ServiceNaming::provisionPanelEmail($user, $canonical, $platform);
        $totalGb = $this->isPerGb($plan) ? (int) $volumeGb : (int) ($plan->traffic_gb ?? 0);
        $totalBytes = $totalGb > 0 ? InboundTraffic::capTrafficBytes($totalGb * 1073741824) : 0;
        $expiresAt = (int) ($plan->duration_days ?? 0) > 0
            ? now()->addDays((int) $plan->duration_days)
            : null;
        $subId = substr(md5($email.microtime(true)), 0, 16);

        $row = [
            'user_id' => $userId,
            'panel_id' => $nodeId,
            'inbound_id' => $inboundRef,
            'xui_client_id' => $uuid,
            'xui_client_uuid' => $uuid,
            'email' => $email,
            'remark' => $canonical,
            'plan_id' => (int) $plan->id,
            'expires_at' => $expiresAt,
            'total_traffic' => $totalBytes,
            'sub_id' => $subId,
            'provision_type' => 'plan',
            'client_enabled' => 1,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('svp_services', 'panel_driver')) {
            $row['panel_driver'] = 'native';
        }

        $service = SvpService::query()->create($row);
        $push = $this->pushLocalConfig();
        if (empty($push['ok'])) {
            DB::table('svp_services')->where('id', $service->id)->delete();

            return new ProvisionResult(
                ok: false,
                reason: 'native_config_push_failed',
                detail: $push['detail'] ?? ($push['reason'] ?? null),
            );
        }

        return new ProvisionResult(ok: true, reason: 'ok', serviceId: (int) $service->id);
    }

    /** @return array{ok:bool, reason?:string, detail?:string} */
    public function pushLocalConfig(): array
    {
        return $this->pushNodeConfig($this->localNode->localNodeId());
    }

    /** @return array{ok:bool, reason?:string, detail?:string} */
    public function pushNodeConfig(int $nodeId): array
    {
        $nodeId = $nodeId > 0 ? $nodeId : $this->localNode->localNodeId();
        if ($nodeId < 1) {
            return ['ok' => false, 'reason' => 'node_missing'];
        }

        $node = $this->localNode->localNode();
        if (! $node) {
            return ['ok' => false, 'reason' => 'node_not_found'];
        }

        $config = $this->configBuilder->buildForNode($nodeId);
        $result = $this->agent->applyConfig($node, $config);
        if (! empty($result['ok'])) {
            DB::table('svp_xray_nodes')->where('id', $nodeId)->update([
                'last_health_at' => now(),
                'last_health_status' => 'applied',
            ]);
        }

        return $result;
    }

    protected function isPerGb(SvpPlan $plan): bool
    {
        return (string) ($plan->category ?? '') === 'per_gb' || (int) ($plan->traffic_gb ?? 0) === 0;
    }

    protected function resolveInboundRef(SvpPlan $plan): int
    {
        if (Schema::hasColumn('svp_plans', 'xray_inbound_ref')) {
            $ref = (int) ($plan->xray_inbound_ref ?? 0);
            if ($ref > 0) {
                return $ref;
            }
        }

        return max(0, (int) ($plan->inbound_id ?? 0));
    }
}
