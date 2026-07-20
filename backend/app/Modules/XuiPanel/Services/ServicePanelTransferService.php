<?php

namespace App\Modules\XuiPanel\Services;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\PasarGuard\Services\PasarGuardClient;
use App\Support\Xui\InboundTraffic;
use App\Support\Xui\ServiceNaming;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ServicePanelTransferService
{
    public function __construct(
        protected XuiClient $xui,
        protected ConfigsSyncService $configs,
        protected UserBotNotifyService $notify,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function transferFromPayload(array $payload, ?Authenticatable $actor): array
    {
        $targetPanelId = (int) ($payload['target_panel_id'] ?? 0);
        $targetPlanId = (int) ($payload['target_plan_id'] ?? 0);
        if ($targetPanelId < 1) {
            return svp_err('invalid_target_panel');
        }

        $serviceIds = [];
        if (isset($payload['service_ids']) && is_array($payload['service_ids'])) {
            foreach ($payload['service_ids'] as $x) {
                $n = (int) $x;
                if ($n > 0) {
                    $serviceIds[] = $n;
                }
            }
        }
        $single = (int) ($payload['service_id'] ?? 0);
        if ($single > 0) {
            $serviceIds[] = $single;
        }
        $serviceIds = array_values(array_unique($serviceIds));
        $serviceIds = array_slice($serviceIds, 0, 20);
        if ($serviceIds === []) {
            return svp_err('empty_items');
        }

        $actorLabel = $actor && method_exists($actor, 'getAttribute')
            ? (string) ($actor->username ?? $actor->email ?? '')
            : '';

        $failed = [];
        $okn = 0;
        foreach ($serviceIds as $sid) {
            $r = $this->transferOne($sid, $targetPanelId, $targetPlanId, $actorLabel);
            if (! empty($r['ok'])) {
                $okn++;
            } else {
                $failed[] = [
                    'service_id' => $sid,
                    'reason' => (string) ($r['reason'] ?? $r['message'] ?? 'failed'),
                ];
            }
        }

        return array_merge(
            empty($failed) ? svp_ok() : ['ok' => false, 'message' => 'partial'],
            ['data' => ['succeeded' => $okn, 'failed' => $failed]]
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function transferOne(int $serviceId, int $targetPanelId, int $targetPlanId = 0, string $actorLabel = ''): array
    {
        if ($serviceId < 1 || $targetPanelId < 1) {
            return svp_err('bad_params');
        }

        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc || L2tpProvisionerService::isL2tp($svc)) {
            return svp_err('bad_service');
        }

        $sourcePanelId = max(1, (int) ($svc->panel_id ?? 1));
        $sourceInboundId = (int) ($svc->inbound_id ?? 0);
        if ($sourceInboundId < 1) {
            return svp_err('bad_inbound');
        }
        if ($sourcePanelId === $targetPanelId) {
            return svp_ok(['service_id' => $serviceId, 'panel_id' => $targetPanelId]);
        }

        $plan = $this->resolveTargetPlan($targetPanelId, $targetPlanId);
        if (! $plan) {
            return svp_err('target_plan_not_found');
        }
        $targetInboundId = (int) ($plan->inbound_id ?? 0);
        if ($targetInboundId < 1) {
            return svp_err('target_inbound_missing');
        }

        $remainingBytes = $this->remainingQuotaBytes($svc);
        $remainingSecs = $this->remainingSeconds($svc);
        $expiryMs = $remainingSecs > 0 ? ((time() + $remainingSecs) * 1000) : 0;
        $panelQuota = InboundTraffic::panelClientTotalgbJsonValue($remainingBytes);

        $user = DB::table('svp_users')->where('id', (int) $svc->user_id)->first();
        $canonical = ServiceNaming::provisionCanonicalLabel($user, null, 1);
        $newEmail = ServiceNaming::provisionPanelEmail($user, $canonical, null);
        $newUuid = '';
        $newSubId = '';

        $create = $this->runOnPanel($targetPanelId, function ($client) use (
            $targetInboundId,
            $panelQuota,
            $expiryMs,
            $svc,
            $canonical,
            $targetPlanId,
            &$newEmail,
            &$newUuid,
            &$newSubId
        ) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'reason' => 'target_login'];
            }

            if ($client instanceof PasarGuardClient) {
                $templateId = 0;
                if ($targetPlanId > 0 && Schema::hasColumn('svp_plans', 'panel_template_id')) {
                    $templateId = (int) (DB::table('svp_plans')->where('id', $targetPlanId)->value('panel_template_id') ?? 0);
                }
                $created = $client->userCreate([
                    'email' => $newEmail,
                    'enable' => true,
                    'totalGB' => $panelQuota,
                    'expiryTime' => (int) $expiryMs,
                    'remark' => (string) ($svc->remark ?? $canonical),
                    'comment' => (string) ($svc->remark ?? $canonical),
                ], [$targetInboundId], $templateId);
                if (empty($created['ok'])) {
                    return ['ok' => false, 'reason' => 'target_add_failed'];
                }
                $cl = is_array($created['client'] ?? null) ? $created['client'] : $client->clientGetV3($newEmail);
                if (! is_array($cl) || ! $client->isLikelyPanelClientId((string) ($cl['id'] ?? ''))) {
                    return ['ok' => false, 'reason' => 'target_verify_failed'];
                }
                $newUuid = (string) ($cl['id'] ?? '');
                $newSubId = $client->pasarguardSubIdForStorage($cl, $targetInboundId);

                return ['ok' => true];
            }

            $inbound = $client->inboundGet($targetInboundId);
            if (! is_array($inbound)) {
                return ['ok' => false, 'reason' => 'target_inbound'];
            }
            $newUuid = (string) $client->getNewUuid();
            if ($newUuid === '' || ! $client->isLikelyClientUuid($newUuid)) {
                return ['ok' => false, 'reason' => 'new_uuid'];
            }
            $newSubId = substr(md5($newEmail.microtime(true)), 0, 16);
            $template = $this->inboundTemplateClient($inbound);
            $clientRow = is_array($template) ? $template : [];
            $clientRow['id'] = $newUuid;
            $clientRow['email'] = $newEmail;
            $clientRow['enable'] = true;
            $clientRow['subId'] = $newSubId;
            $clientRow['remark'] = (string) ($svc->remark ?? $canonical);
            $clientRow['totalGB'] = $panelQuota;
            $clientRow['expiryTime'] = (int) $expiryMs;
            foreach (['up', 'down', 'total', 'lastOnline'] as $dropKey) {
                unset($clientRow[$dropKey]);
            }
            $payload = [
                'id' => $targetInboundId,
                'settings' => json_encode(['clients' => [$clientRow]]),
            ];
            $res = $client->addClientRequest($payload);
            if (! $client->addClientRequestOk($res)) {
                return ['ok' => false, 'reason' => 'target_add_failed'];
            }
            $inb2 = $client->inboundGet($targetInboundId);
            $cl2 = is_array($inb2) ? $client->inboundClientByEmail($inb2, $newEmail) : null;
            if (! is_array($cl2)) {
                return ['ok' => false, 'reason' => 'target_verify_failed'];
            }

            return ['ok' => true];
        });

        if (empty($create['ok'])) {
            return svp_err((string) ($create['reason'] ?? 'target_unknown'));
        }

        $del = $this->deleteSourceClient($sourcePanelId, $sourceInboundId, (string) ($svc->xui_client_id ?? ''), (string) ($svc->email ?? ''));
        if (empty($del['ok'])) {
            $this->deleteTargetClient($targetPanelId, $targetInboundId, $newEmail);

            return svp_err((string) ($del['reason'] ?? 'source_delete_failed'));
        }

        $expiresAt = $remainingSecs > 0 ? gmdate('Y-m-d H:i:s', time() + $remainingSecs) : null;
        DB::table('svp_services')->where('id', $serviceId)->update([
            'panel_id' => $targetPanelId,
            'inbound_id' => $targetInboundId,
            'plan_id' => (int) $plan->id,
            'xui_client_id' => $newUuid,
            'xui_client_uuid' => $newUuid,
            'email' => $newEmail,
            'sub_id' => $newSubId,
            'expires_at' => $expiresAt,
            'total_traffic' => (int) $remainingBytes,
            'remark' => (string) ($plan->name ?? $svc->remark ?? ''),
        ]);

        $verify = DB::table('svp_services')->where('id', $serviceId)->first();
        if (
            ! $verify
            || (int) ($verify->panel_id ?? 0) !== $targetPanelId
            || (int) ($verify->inbound_id ?? 0) !== $targetInboundId
            || (string) ($verify->email ?? '') !== $newEmail
        ) {
            $this->deleteTargetClient($targetPanelId, $targetInboundId, $newEmail);

            return svp_err('transfer_db_failed');
        }

        $this->configs->syncInboundsAfterMutation($sourcePanelId, [$sourceInboundId]);
        $this->configs->syncInboundsAfterMutation($targetPanelId, [$targetInboundId]);
        $this->notifyAfterTransfer($svc, $actorLabel, $plan);

        return svp_ok([
            'service_id' => $serviceId,
            'panel_id' => $targetPanelId,
            'plan_id' => (int) $plan->id,
        ]);
    }

    /** @return array<string, mixed> */
    public function reassignServiceDbOnly(int $serviceId, int $targetPanelId, int $targetPlanId, string $actorLabel = ''): array
    {
        if ($serviceId < 1 || $targetPanelId < 1 || $targetPlanId < 1) {
            return svp_err('bad_params');
        }
        $svc = DB::table('svp_services')->where('id', $serviceId)->whereNull('deleted_at')->first();
        if (! $svc || L2tpProvisionerService::isL2tp($svc)) {
            return svp_err('bad_service');
        }
        $sourcePanelId = max(1, (int) ($svc->panel_id ?? 1));
        if ((int) ($svc->inbound_id ?? 0) < 1) {
            return svp_err('bad_inbound');
        }
        if ($sourcePanelId === $targetPanelId && (int) ($svc->plan_id ?? 0) === $targetPlanId) {
            return svp_ok();
        }
        $plan = $this->resolveTargetPlan($targetPanelId, $targetPlanId);
        if (! $plan) {
            return svp_err('target_plan_not_found');
        }
        $targetInboundId = (int) ($plan->inbound_id ?? 0);
        if ($targetInboundId < 1) {
            return svp_err('target_inbound_missing');
        }
        $targetInboundIds = [$targetInboundId];
        if (Schema::hasColumn('svp_plans', 'inbound_ids') && ! empty($plan->inbound_ids)) {
            $dec = json_decode((string) $plan->inbound_ids, true);
            if (is_array($dec) && $dec !== []) {
                $targetInboundIds = array_values(array_filter(array_map('intval', $dec), fn ($v) => $v > 0));
            }
        }
        $update = [
            'panel_id' => $targetPanelId,
            'inbound_id' => $targetInboundId,
            'plan_id' => (int) $plan->id,
            'remark' => (string) ($plan->name ?? $svc->remark ?? ''),
        ];
        if (Schema::hasColumn('svp_services', 'inbound_ids')) {
            $update['inbound_ids'] = json_encode($targetInboundIds);
        }
        DB::table('svp_services')->where('id', $serviceId)->update($update);
        $verify = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $verify || (int) ($verify->panel_id ?? 0) !== $targetPanelId || (int) ($verify->plan_id ?? 0) !== (int) $plan->id) {
            return svp_err('reassign_db_failed');
        }
        $iids = array_values(array_unique(array_merge([(int) ($svc->inbound_id ?? 0)], $targetInboundIds)));
        $this->configs->syncInboundsAfterMutation($sourcePanelId, $iids);
        if ($targetPanelId !== $sourcePanelId) {
            $this->configs->syncInboundsAfterMutation($targetPanelId, $iids);
        }
        $this->notifyAfterTransfer($svc, $actorLabel, $plan);

        return svp_ok(['service_id' => $serviceId]);
    }

    protected function resolveTargetPlan(int $panelId, int $targetPlanId): ?object
    {
        if ($targetPlanId > 0) {
            $plan = SvpPlan::query()->find($targetPlanId);

            return ($plan && (int) ($plan->panel_id ?? 0) === $panelId) ? $plan : null;
        }

        return SvpPlan::query()
            ->where('panel_id', $panelId)
            ->where('active', 1)
            ->where('inbound_id', '>', 0)
            ->where(function ($q) {
                $q->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            })
            ->orderBy('sort_order')
            ->orderBy('id')
            ->first();
    }

    protected function remainingQuotaBytes(object $svc): int
    {
        $total = (int) ($svc->total_traffic ?? 0);
        $used = (int) ($svc->used_traffic ?? 0);
        if (Schema::hasTable('svp_panel_inbound_clients')) {
            $row = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', (int) ($svc->panel_id ?? 1))
                ->where('inbound_id', (int) ($svc->inbound_id ?? 0))
                ->where('email', (string) ($svc->email ?? ''))
                ->first();
            if ($row) {
                $total = (int) ($row->limit_bytes ?? $total);
                $used = (int) ($row->used_bytes ?? $used);
            }
        }
        if ($total < 1) {
            return 0;
        }

        return max(0, $total - $used);
    }

    protected function remainingSeconds(object $svc): int
    {
        $exp = isset($svc->expires_at) ? (string) $svc->expires_at : '';
        if ($exp === '') {
            return 0;
        }
        $ts = strtotime($exp.' UTC');

        return ($ts !== false) ? max(0, $ts - time()) : 0;
    }

    /** @param  array<string, mixed>  $inbound */
    protected function inboundTemplateClient(array $inbound): array
    {
        $settings = $inbound['settings'] ?? '';
        $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
        if (! is_array($dec) || empty($dec['clients']) || ! is_array($dec['clients'])) {
            return [];
        }

        return is_array($dec['clients'][0] ?? null) ? $dec['clients'][0] : [];
    }

    /** @return array{ok:bool, reason?:string} */
    protected function deleteSourceClient(int $panelId, int $inboundId, string $xuiClientId, string $email): array
    {
        return $this->runOnPanel($panelId, function ($client) use ($inboundId, $xuiClientId, $email) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'reason' => 'source_login'];
            }
            $client->delClient($inboundId, $xuiClientId, $email);

            return ['ok' => true];
        });
    }

    protected function deleteTargetClient(int $panelId, int $inboundId, string $email): void
    {
        $this->runOnPanel($panelId, function ($client) use ($inboundId, $email) {
            if ($client->loginWithRetries(4, 220000)) {
                $client->delClient($inboundId, $email, $email);
            }
        });
    }

    protected function runOnPanel(int $panelId, callable $fn): mixed
    {
        if (function_exists('svp_modules') && svp_modules()->isEnabled('pasarguard') && class_exists(\App\Modules\PasarGuard\Services\PanelClientFactory::class)) {
            return app(\App\Modules\PasarGuard\Services\PanelClientFactory::class)->runWithPanel($panelId, $fn);
        }

        return $this->xui->runWithPanel($panelId, function () use ($fn) {
            return $fn($this->xui);
        });
    }

    protected function notifyAfterTransfer(object $svc, string $actorLabel, ?object $plan): void
    {
        $user = SvpUser::query()->find((int) ($svc->user_id ?? 0));
        if (! $user) {
            return;
        }
        $msg = '🔁 سرویس شما به سرور جدید منتقل شد.';
        if ($plan && ! empty($plan->name)) {
            $msg .= "\nپلن جدید: ".(string) $plan->name;
        }
        if (trim($actorLabel) !== '') {
            $msg .= "\nتوسط: ".$actorLabel;
        }
        $this->notify->sendToUser($user, $msg);
    }
}
