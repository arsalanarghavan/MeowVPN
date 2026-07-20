<?php

namespace App\Services\Commerce;

use App\Models\SvpPlan;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\XrayCore\Services\PanelDriverResolver;
use App\Support\Xui\InboundTraffic;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceProvisioner
{
    public function __construct(
        protected PanelDriverResolver $drivers,
        protected L2tpProvisionerService $l2tp,
    ) {}

    /**
     * @return array{ok:bool, service_id?:int, reason:string, detail?:string}
     */
    public function createFromPlan(int $userId, int $planId, ?int $volumeGb = null, ?string $platform = null): array
    {
        $plan = SvpPlan::query()->find($planId);
        if (! $plan || ! $plan->active) {
            return ['ok' => false, 'reason' => 'plan_missing_or_inactive'];
        }
        if ($this->isPerGb($plan) && ($volumeGb === null || $volumeGb < 1)) {
            return ['ok' => false, 'reason' => 'volume_out_of_range'];
        }
        if ((string) ($plan->service_type ?? 'xray') === 'l2tp') {
            return $this->createL2tpFromPlan($userId, $planId, $plan, $volumeGb);
        }

        $usesNative = $this->drivers->planUsesNative($plan);
        if ($usesNative) {
            if ($this->drivers->resolveInboundRef($plan) < 1) {
                return ['ok' => false, 'reason' => 'inbound_missing'];
            }
        } elseif ((int) ($plan->inbound_id ?? 0) < 1) {
            return ['ok' => false, 'reason' => 'inbound_missing'];
        }

        $result = $this->drivers->forPlan($plan)->provisionFromPlan($userId, $plan, $volumeGb, $platform);

        return $result->toArray();
    }

    /**
     * @return array{ok:bool, service_id?:int, reason:string, detail?:string}
     */
    protected function createL2tpFromPlan(int $userId, int $planId, SvpPlan $plan, ?int $volumeGb): array
    {
        if (! svp_modules()->isEnabled('l2tp')) {
            return ['ok' => false, 'reason' => 'l2tp_module_disabled'];
        }

        $serverId = $this->resolveL2tpServerId($plan);
        if ($serverId < 1) {
            return ['ok' => false, 'reason' => 'l2tp_server_missing'];
        }

        $created = $this->l2tp->createUser($serverId);
        if (empty($created['ok'])) {
            return [
                'ok' => false,
                'reason' => (string) ($created['reason'] ?? 'l2tp_provision_failed'),
                'detail' => $created['detail'] ?? null,
            ];
        }

        $username = (string) ($created['username'] ?? '');
        $password = (string) ($created['password'] ?? '');
        if ($username === '' || $password === '') {
            return ['ok' => false, 'reason' => 'l2tp_credentials_missing'];
        }

        $totalGb = $this->isPerGb($plan) ? (int) $volumeGb : (int) ($plan->traffic_gb ?? 0);
        $totalBytes = $totalGb > 0 ? InboundTraffic::capTrafficBytes($totalGb * 1073741824) : 0;
        $expiresAt = (int) ($plan->duration_days ?? 0) > 0
            ? now()->addDays((int) $plan->duration_days)
            : null;
        $user = DB::table('svp_users')->where('id', $userId)->first();
        $canonical = ServiceNaming::provisionCanonicalLabel($user, null, 1);
        $email = $username.'@l2tp.local';

        $row = [
            'user_id' => $userId,
            'panel_id' => max(0, (int) ($plan->panel_id ?? 0)),
            'inbound_id' => 0,
            'email' => $email,
            'remark' => $canonical,
            'plan_id' => $planId,
            'expires_at' => $expiresAt,
            'total_traffic' => $totalBytes,
            'provision_type' => 'plan',
            'service_type' => 'l2tp',
            'l2tp_server_id' => $serverId,
            'l2tp_username' => $username,
            'client_slots' => max(1, (int) ($plan->clients_count ?? 1)),
            'client_enabled' => 1,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('svp_services', 'l2tp_password')) {
            $row['l2tp_password'] = $password;
        }
        if (Schema::hasColumn('svp_services', 'l2tp_password_enc')) {
            $row['l2tp_password_enc'] = Crypt::encryptString($password);
        }

        $serviceId = (int) DB::table('svp_services')->insertGetId($row);

        return ['ok' => true, 'service_id' => $serviceId, 'reason' => 'ok'];
    }

    protected function resolveL2tpServerId(SvpPlan $plan): int
    {
        $sid = (int) ($plan->l2tp_server_id ?? 0);
        if ($sid > 0) {
            return $sid;
        }

        $lineId = (int) ($plan->wholesale_line_id ?? 0);
        if ($lineId > 0 && Schema::hasTable('svp_reseller_wholesale_lines')) {
            $fromLine = (int) DB::table('svp_reseller_wholesale_lines')
                ->where('id', $lineId)
                ->value('default_l2tp_server_id');
            if ($fromLine > 0) {
                return $fromLine;
            }
        }

        if (! Schema::hasTable('svp_l2tp_servers')) {
            return 0;
        }

        return (int) DB::table('svp_l2tp_servers')->where('active', 1)->orderBy('id')->value('id');
    }

    protected function isPerGb(SvpPlan $plan): bool
    {
        return (string) ($plan->category ?? '') === 'per_gb' || (int) ($plan->traffic_gb ?? 0) === 0;
    }

    /**
     * @param  array<string, mixed>|object  $svc
     * @return array{ok:bool, action?:string, reason?:string, detail?:string}
     */
    public function addClientFromServiceRow(array|object $svc): array
    {
        return $this->drivers->forService($svc)->addClientFromServiceRow($svc);
    }
}
