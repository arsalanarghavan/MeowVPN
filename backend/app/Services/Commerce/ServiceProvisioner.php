<?php

namespace App\Services\Commerce;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Support\Xui\InboundTraffic;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServiceProvisioner
{
    public function __construct(
        protected XuiClient $xui,
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
        if ((int) ($plan->inbound_id ?? 0) < 1) {
            return ['ok' => false, 'reason' => 'inbound_missing'];
        }
        $panelId = max(1, (int) ($plan->panel_id ?? 1));

        return $this->xui->runWithPanel($panelId, function () use ($userId, $planId, $plan, $volumeGb, $platform) {
            return $this->createXrayOnPanel($userId, $planId, $plan, $volumeGb, $platform);
        });
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

    /**
     * @return array{ok:bool, service_id?:int, reason:string, detail?:string}
     */
    protected function createXrayOnPanel(int $userId, int $planId, SvpPlan $plan, ?int $volumeGb, ?string $platform): array
    {
        if (! $this->xui->loginWithRetries(7, 320000)) {
            return ['ok' => false, 'reason' => 'login_fail'];
        }
        $inbound = $this->xui->inboundGet((int) $plan->inbound_id);
        if (! is_array($inbound)) {
            return ['ok' => false, 'reason' => 'inbound_not_found', 'detail' => 'id='.(int) $plan->inbound_id];
        }
        $uuid = $this->xui->getNewUuid();
        if (! is_string($uuid) || ! $this->xui->isLikelyClientUuid($uuid)) {
            return ['ok' => false, 'reason' => 'uuid_missing'];
        }
        $user = DB::table('svp_users')->where('id', $userId)->first();
        $canonical = ServiceNaming::provisionCanonicalLabel($user, $platform, 1);
        $email = ServiceNaming::provisionPanelEmail($user, $canonical, $platform);
        $totalGb = $this->isPerGb($plan) ? (int) $volumeGb : (int) ($plan->traffic_gb ?? 0);
        $totalBytes = $totalGb > 0 ? InboundTraffic::capTrafficBytes($totalGb * 1073741824) : 0;
        $panelQuota = InboundTraffic::panelClientTotalgbJsonValue($totalBytes);
        $expiryMs = 0;
        if ((int) ($plan->duration_days ?? 0) > 0) {
            $expiryMs = (time() + (int) $plan->duration_days * 86400) * 1000;
        }
        $subId = substr(md5($email.microtime(true)), 0, 16);
        $defUsers = max(0, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
        $newClient = [
            'id' => (string) $uuid,
            'email' => $email,
            'enable' => true,
            'flow' => '',
            'limitIp' => $defUsers,
            'totalGB' => $panelQuota,
            'expiryTime' => $expiryMs,
            'subId' => $subId,
            'remark' => $canonical,
        ];
        $template = $this->inboundTemplateClient($inbound);
        if ($template !== []) {
            $newClient = array_merge($template, $newClient);
            $newClient['id'] = (string) $uuid;
            $newClient['email'] = $email;
            $newClient['totalGB'] = $panelQuota;
            $newClient['expiryTime'] = $expiryMs;
        }
        foreach (['up', 'down', 'total', 'lastOnline'] as $strip) {
            unset($newClient[$strip]);
        }
        $payload = [
            'id' => (int) $plan->inbound_id,
            'settings' => json_encode(['clients' => [$newClient]]),
        ];
        $addReq = null;
        $ok = false;
        for ($ac = 0; $ac < 4; $ac++) {
            if ($ac > 0) {
                usleep(320000 + $ac * 120000);
                $this->xui->clearSession();
                $this->xui->loginWithRetries(4, 280000);
            }
            $addReq = $this->xui->addClientRequest($payload);
            $ok = $this->xui->addClientRequestOk($addReq);
            if ($ok) {
                break;
            }
        }
        if (! $ok) {
            return [
                'ok' => false,
                'reason' => 'addclient_panel',
                'detail' => $this->xui->panelJsonMsg($addReq['json'] ?? null),
            ];
        }
        $iid = (int) $plan->inbound_id;
        $verified = $this->waitForClientInInbound($iid, $email, 8);
        if (! is_array($verified)) {
            $this->xui->delClient($iid, (string) $uuid, $email);

            return ['ok' => false, 'reason' => 'panel_verify_failed'];
        }
        if (! empty($verified['id']) && $this->xui->isLikelyClientUuid((string) $verified['id'])) {
            $uuid = (string) $verified['id'];
        }
        if (! empty($verified['subId'])) {
            $subId = (string) $verified['subId'];
        }
        $expiresAt = (int) ($plan->duration_days ?? 0) > 0
            ? now()->addDays((int) $plan->duration_days)
            : null;
        $service = SvpService::query()->create([
            'user_id' => $userId,
            'panel_id' => max(1, (int) ($plan->panel_id ?? 1)),
            'inbound_id' => $iid,
            'xui_client_id' => $uuid,
            'xui_client_uuid' => $uuid,
            'email' => $email,
            'remark' => $canonical,
            'plan_id' => $planId,
            'expires_at' => $expiresAt,
            'total_traffic' => $totalBytes,
            'sub_id' => $subId,
            'provision_type' => 'plan',
            'client_enabled' => 1,
            'created_at' => now(),
        ]);

        return ['ok' => true, 'service_id' => (int) $service->id, 'reason' => 'ok'];
    }

    /** @return array<string, mixed> */
    protected function inboundTemplateClient(array $inbound): array
    {
        $settings = $inbound['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'][0] ?? null)) {
            return [];
        }
        $tpl = $dec['clients'][0];
        unset($tpl['email'], $tpl['id'], $tpl['subId'], $tpl['up'], $tpl['down'], $tpl['total']);

        return is_array($tpl) ? $tpl : [];
    }

    /** @return array<string, mixed>|null */
    protected function waitForClientInInbound(int $inboundId, string $email, int $maxAttempts): ?array
    {
        for ($i = 0; $i < $maxAttempts; $i++) {
            if ($i > 0) {
                usleep(250000);
            }
            $inb = $this->xui->inboundGet($inboundId);
            $cl = $this->xui->inboundClientByEmail($inb, $email);
            if (is_array($cl)) {
                return $cl;
            }
        }

        return null;
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
        $row = is_array($svc) ? $svc : (array) $svc;
        $panelId = max(1, (int) ($row['panel_id'] ?? 1));

        return $this->xui->runWithPanel($panelId, function () use ($row) {
            return $this->addClientFromServiceRowOnPanel($row);
        });
    }

    /**
     * @param  array<string, mixed>  $svc
     * @return array{ok:bool, action?:string, reason?:string, detail?:string}
     */
    protected function addClientFromServiceRowOnPanel(array $svc): array
    {
        $email = trim((string) ($svc['email'] ?? ''));
        $iid = (int) ($svc['inbound_id'] ?? 0);
        if ($email === '' || $iid < 1) {
            return ['ok' => false, 'reason' => 'bad_service_row'];
        }
        if (! $this->xui->loginWithRetries(7, 320000)) {
            return ['ok' => false, 'reason' => 'login_fail'];
        }
        $inbound = $this->xui->inboundGet($iid);
        if (! is_array($inbound)) {
            return ['ok' => false, 'reason' => 'inbound_not_found', 'detail' => 'id='.$iid];
        }
        if ($this->xui->inboundClientByEmail($inbound, $email)) {
            return ['ok' => true, 'action' => 'already_on_panel'];
        }

        $uuid = trim((string) ($svc['xui_client_uuid'] ?? $svc['xui_client_id'] ?? ''));
        if ($uuid === '' || ! $this->xui->isLikelyClientUuid($uuid)) {
            $uuid = (string) $this->xui->getNewUuid();
        }
        if ($uuid === '' || ! $this->xui->isLikelyClientUuid($uuid)) {
            return ['ok' => false, 'reason' => 'uuid_missing'];
        }
        $totalBytes = InboundTraffic::capTrafficBytes((int) ($svc['total_traffic'] ?? 0));
        $panelQuota = InboundTraffic::panelClientTotalgbJsonValue($totalBytes);
        $expiryMs = 0;
        if (! empty($svc['expires_at'])) {
            $ts = strtotime((string) $svc['expires_at'].' UTC');
            if ($ts > 0) {
                $expiryMs = $ts * 1000;
            }
        }
        $subId = trim((string) ($svc['sub_id'] ?? ''));
        if ($subId === '') {
            $subId = substr(md5($email.microtime(true)), 0, 16);
        }
        $remark = trim((string) ($svc['remark'] ?? ''));
        $limitIp = (int) ($svc['panel_limit_ip'] ?? $svc['limit_ip'] ?? 0);
        if ($limitIp < 1) {
            $limitIp = max(0, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
        }

        $template = $this->inboundTemplateClient($inbound);
        $newClient = [
            'id' => $uuid,
            'email' => $email,
            'limitIp' => $limitIp,
            'totalGB' => $panelQuota,
            'expiryTime' => $expiryMs,
            'enable' => true,
            'subId' => $subId,
            'remark' => $remark,
        ];
        if ($template !== []) {
            $newClient = array_merge($template, $newClient);
            $newClient['id'] = $uuid;
            $newClient['email'] = $email;
        }
        $payload = [
            'id' => $iid,
            'settings' => json_encode(['clients' => [$newClient]]),
        ];
        $addReq = $this->xui->addClientRequest($payload);
        if (! $this->xui->addClientRequestOk($addReq)) {
            return [
                'ok' => false,
                'reason' => 'addclient_panel',
                'detail' => $this->xui->panelJsonMsg($addReq['json'] ?? null),
            ];
        }

        return ['ok' => true, 'action' => 'created'];
    }
}
