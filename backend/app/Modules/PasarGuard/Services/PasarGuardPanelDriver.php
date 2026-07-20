<?php

namespace App\Modules\PasarGuard\Services;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Modules\XrayCore\DTO\ProvisionResult;
use App\Modules\XrayCore\DTO\TrafficStats;
use App\Modules\XrayCore\Services\PanelDriverInterface;
use App\Support\Xui\InboundTraffic;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PasarGuardPanelDriver implements PanelDriverInterface
{
    public function __construct(protected PasarGuardClient $pg) {}

    public function driverType(): string
    {
        return 'pasarguard';
    }

    public function provisionFromPlan(int $userId, SvpPlan $plan, ?int $volumeGb, ?string $platform): ProvisionResult
    {
        $panelId = max(1, (int) ($plan->panel_id ?? 1));

        $result = $this->pg->runWithPanel($panelId, function () use ($userId, $plan, $volumeGb, $platform) {
            return $this->createOnBoundPanel($userId, (int) $plan->id, $plan, $volumeGb, $platform);
        });

        if (empty($result['ok'])) {
            return new ProvisionResult(
                ok: false,
                reason: (string) ($result['reason'] ?? 'provision_failed'),
                detail: $result['detail'] ?? null,
            );
        }

        return new ProvisionResult(
            ok: true,
            reason: 'ok',
            serviceId: (int) ($result['service_id'] ?? 0),
        );
    }

    /** @param  array<string, mixed>|object  $svc */
    public function addClientFromServiceRow(array|object $svc): array
    {
        $row = is_array($svc) ? $svc : (array) $svc;
        $panelId = max(1, (int) ($row['panel_id'] ?? 1));

        return $this->pg->runWithPanel($panelId, function () use ($row) {
            return $this->addClientFromServiceRowOnPanel($row);
        });
    }

    /** @param  object  $svc */
    public function buildSubscriptionUris(object $svc): array
    {
        $email = trim((string) ($svc->email ?? ''));
        if ($email === '') {
            return [];
        }
        $panelId = max(1, (int) ($svc->panel_id ?? 1));

        return $this->pg->runWithPanel($panelId, function () use ($email) {
            if (! $this->pg->loginWithRetries(4, 200000)) {
                return [];
            }

            return $this->pg->clientLinksV3($email);
        });
    }

    /** @param  object  $svc */
    public function syncTraffic(object $svc): ?TrafficStats
    {
        return null;
    }

    /** @param  object  $svc */
    public function disableClient(object $svc): bool
    {
        $panelId = max(1, (int) ($svc->panel_id ?? 1));
        $email = trim((string) ($svc->email ?? ''));
        if ($email === '') {
            return false;
        }

        return (bool) $this->pg->runWithPanel($panelId, function () use ($email) {
            if (! $this->pg->loginWithRetries()) {
                return false;
            }
            $res = $this->pg->clientUpdateV3($email, ['enable' => false]);

            return $this->pg->responseIsSuccess($res);
        });
    }

    /** @param  object  $svc */
    public function deleteClient(object $svc): bool
    {
        $panelId = max(1, (int) ($svc->panel_id ?? 1));
        $result = $this->pg->runWithPanel($panelId, fn () => $this->pg->deleteClient(
            ['id' => $panelId],
            (int) ($svc->id ?? 0),
        ));

        return ! empty($result['ok']);
    }

    /** @param  object  $svc */
    public function renewClient(object $svc, int $days, int $gb): bool
    {
        return false;
    }

    /**
     * @return array{ok:bool, service_id?:int, reason:string, detail?:string}
     */
    protected function createOnBoundPanel(int $userId, int $planId, SvpPlan $plan, ?int $volumeGb, ?string $platform): array
    {
        $groupIds = $this->groupIdsFromPlan($plan);
        if ($groupIds === []) {
            return ['ok' => false, 'reason' => 'inbound_missing'];
        }
        $primaryGid = (int) $groupIds[0];
        if (! $this->pg->loginWithRetries(6, 300000)) {
            return ['ok' => false, 'reason' => 'panel_login'];
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
        $defUsers = max(0, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
        $newClient = [
            'email' => $email,
            'enable' => true,
            'limitIp' => $defUsers,
            'totalGB' => $panelQuota,
            'expiryTime' => $expiryMs,
            'comment' => $canonical,
            'remark' => $canonical,
        ];
        $templateId = (int) ($plan->panel_template_id ?? 0);
        $created = $this->pg->userCreate($newClient, $groupIds, $templateId);
        if (empty($created['ok'])) {
            return [
                'ok' => false,
                'reason' => 'addclient_panel',
                'detail' => $this->pg->panelJsonMsg($created['json'] ?? null),
            ];
        }
        $cl = is_array($created['client'] ?? null) ? $created['client'] : $this->pg->clientGetV3($email);
        $pgid = is_array($cl) ? (string) ($cl['id'] ?? '') : '';
        $sub = is_array($cl) ? $this->pg->pasarguardSubIdForStorage($cl, max(1, (int) ($plan->panel_id ?? 1))) : '';
        if ($pgid === '' || ! $this->pg->isLikelyPanelClientId($pgid)) {
            $this->pg->clientDeleteV3($email);

            return ['ok' => false, 'reason' => 'panel_identity_missing'];
        }

        $patch = [
            'totalGB' => $panelQuota,
            'expiryTime' => (int) $expiryMs,
            'enable' => true,
            'limitIp' => $defUsers,
            'comment' => $canonical,
        ];
        $pres = $this->pg->clientUpdateV3($email, $patch, $groupIds);
        if (! $this->pg->responseIsSuccess($pres)) {
            $this->pg->clientDeleteV3($email);

            return [
                'ok' => false,
                'reason' => 'panel_quota_patch_failed',
                'detail' => $this->pg->panelJsonMsg($pres['json'] ?? null),
            ];
        }
        $cl = $this->pg->clientGetV3($email);
        if (! is_array($cl)) {
            $this->pg->clientDeleteV3($email);

            return ['ok' => false, 'reason' => 'panel_verify_failed', 'detail' => 'client missing after quota patch'];
        }

        $expiresAt = (int) ($plan->duration_days ?? 0) > 0
            ? now()->addDays((int) $plan->duration_days)
            : null;
        $serviceRow = [
            'user_id' => $userId,
            'panel_id' => max(1, (int) ($plan->panel_id ?? 1)),
            'inbound_id' => $primaryGid,
            'xui_client_id' => $pgid,
            'xui_client_uuid' => $pgid,
            'email' => $email,
            'remark' => $canonical,
            'plan_id' => $planId,
            'expires_at' => $expiresAt,
            'total_traffic' => $totalBytes,
            'sub_id' => $sub,
            'provision_type' => 'plan',
            'client_enabled' => 1,
            'created_at' => now(),
        ];
        if (Schema::hasColumn('svp_services', 'panel_driver')) {
            $serviceRow['panel_driver'] = 'pasarguard';
        }
        if (Schema::hasColumn('svp_services', 'panel_limit_ip') && $defUsers > 0) {
            $serviceRow['panel_limit_ip'] = $defUsers;
        }

        try {
            $service = SvpService::query()->create($serviceRow);
        } catch (\Throwable) {
            $this->pg->delClient($primaryGid, $pgid, $email);

            return ['ok' => false, 'reason' => 'db_insert'];
        }

        return ['ok' => true, 'service_id' => (int) $service->id, 'reason' => 'ok'];
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
        if (! $this->pg->loginWithRetries(7, 320000)) {
            return ['ok' => false, 'reason' => 'login_fail'];
        }
        if ($this->pg->clientExistsV3($email)) {
            return ['ok' => true, 'action' => 'already_on_panel'];
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
        $limitIp = (int) ($svc['panel_limit_ip'] ?? $svc['limit_ip'] ?? 0);
        if ($limitIp < 1) {
            $limitIp = max(0, (int) (DB::table('svp_settings')->where('key_name', 'default_concurrent_users')->value('value') ?? 2));
        }
        $remark = trim((string) ($svc['remark'] ?? ''));
        $templateId = 0;
        $planId = (int) ($svc['plan_id'] ?? 0);
        if ($planId > 0 && Schema::hasColumn('svp_plans', 'panel_template_id')) {
            $templateId = (int) (DB::table('svp_plans')->where('id', $planId)->value('panel_template_id') ?? 0);
        }
        $created = $this->pg->userCreate([
            'email' => $email,
            'enable' => true,
            'limitIp' => $limitIp,
            'totalGB' => $panelQuota,
            'expiryTime' => $expiryMs,
            'comment' => $remark,
            'remark' => $remark,
        ], [$iid], $templateId);
        if (empty($created['ok'])) {
            return [
                'ok' => false,
                'reason' => 'addclient_panel',
                'detail' => $this->pg->panelJsonMsg($created['json'] ?? null),
            ];
        }

        return ['ok' => true, 'action' => 'created'];
    }

    /** @return array<int> */
    protected function groupIdsFromPlan(SvpPlan $plan): array
    {
        $ids = [];
        if (Schema::hasColumn('svp_plans', 'inbound_ids')) {
            $raw = $plan->inbound_ids ?? null;
            if (is_string($raw) && $raw !== '') {
                $dec = json_decode($raw, true);
                if (is_array($dec)) {
                    foreach ($dec as $v) {
                        $n = (int) $v;
                        if ($n > 0) {
                            $ids[] = $n;
                        }
                    }
                } elseif (preg_match_all('/\d+/', $raw, $m)) {
                    foreach ($m[0] as $v) {
                        $n = (int) $v;
                        if ($n > 0) {
                            $ids[] = $n;
                        }
                    }
                }
            } elseif (is_array($raw)) {
                foreach ($raw as $v) {
                    $n = (int) $v;
                    if ($n > 0) {
                        $ids[] = $n;
                    }
                }
            }
        }
        $primary = (int) ($plan->inbound_id ?? 0);
        if ($primary > 0 && ! in_array($primary, $ids, true)) {
            array_unshift($ids, $primary);
        }

        return array_values(array_unique($ids));
    }

    protected function isPerGb(SvpPlan $plan): bool
    {
        return (string) ($plan->category ?? '') === 'per_gb' || (int) ($plan->traffic_gb ?? 0) === 0;
    }
}
