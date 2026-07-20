<?php

namespace App\Modules\XuiPanel\Services;

use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Jobs\PlanInboundSyncJob;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlanInboundSyncService
{
    public const INLINE_LIMIT = 20;

    public const BATCH_SIZE = 10;

    public function __construct(
        protected PanelClientFactory $panels,
        protected XuiClient $xui,
    ) {}

    /**
     * @param  array<int, int>|null  $oldIds
     * @param  array<int, int>  $newIds
     * @return array{mode:string, total:int, ok?:int, errors?:int}
     */
    public function propagatePlanInbounds(int $planId, ?array $oldIds, array $newIds): array
    {
        $newIds = $this->normalizeIds($newIds);
        $oldIds = is_array($oldIds) ? $this->normalizeIds($oldIds) : [];
        if ($planId < 1 || $oldIds === $newIds) {
            return ['mode' => 'skipped', 'total' => 0];
        }

        $serviceIds = DB::table('svp_services')
            ->where('plan_id', $planId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $total = count($serviceIds);
        if ($total < 1) {
            return ['mode' => 'none', 'total' => 0];
        }

        if ($total <= self::INLINE_LIMIT) {
            $stats = $this->syncServiceIds($serviceIds, $oldIds, $newIds);

            return array_merge(['mode' => 'inline', 'total' => $total], $stats);
        }

        Cache::put($this->jobKey($planId), [
            'old_ids' => $oldIds,
            'new_ids' => $newIds,
            'service_ids' => $serviceIds,
            'offset' => 0,
        ], 3600);
        $this->scheduleQueuedProcessing($planId);

        return ['mode' => 'queued', 'total' => $total];
    }

    public function hasQueuedJob(int $planId): bool
    {
        return Cache::has($this->jobKey($planId));
    }

    public function scheduleQueuedProcessing(int $planId): void
    {
        if ($planId < 1) {
            return;
        }
        PlanInboundSyncJob::dispatch($planId)->delay(now()->addSecond());
    }

    public function processQueuedPlan(int $planId): void
    {
        $job = Cache::get($this->jobKey($planId));
        if (! is_array($job) || empty($job['service_ids']) || ! is_array($job['service_ids'])) {
            Cache::forget($this->jobKey($planId));

            return;
        }
        $oldIds = isset($job['old_ids']) && is_array($job['old_ids']) ? $this->normalizeIds($job['old_ids']) : [];
        $newIds = isset($job['new_ids']) && is_array($job['new_ids']) ? $this->normalizeIds($job['new_ids']) : [];
        $serviceIds = array_map('intval', $job['service_ids']);
        $offset = max(0, (int) ($job['offset'] ?? 0));
        $batch = array_slice($serviceIds, $offset, self::BATCH_SIZE);
        if ($batch === []) {
            Cache::forget($this->jobKey($planId));

            return;
        }
        $this->syncServiceIds($batch, $oldIds, $newIds);
        $offset += count($batch);
        if ($offset >= count($serviceIds)) {
            Cache::forget($this->jobKey($planId));

            return;
        }
        $job['offset'] = $offset;
        Cache::put($this->jobKey($planId), $job, 3600);
        $this->scheduleQueuedProcessing($planId);
    }

    /**
     * @param  array<int, int>  $serviceIds
     * @param  array<int, int>  $oldIds
     * @param  array<int, int>  $newIds
     * @return array{ok:int, errors:int}
     */
    protected function syncServiceIds(array $serviceIds, array $oldIds, array $newIds): array
    {
        $ok = 0;
        $errors = 0;
        foreach ($serviceIds as $sid) {
            if ($sid < 1) {
                continue;
            }
            $svc = DB::table('svp_services')->where('id', $sid)->first();
            if (! $svc) {
                continue;
            }
            $res = $this->syncOneService($svc, $oldIds, $newIds);
            if (! empty($res['ok'])) {
                $ok++;
            } else {
                $errors++;
            }
        }

        return ['ok' => $ok, 'errors' => $errors];
    }

    /**
     * @param  array<int, int>  $oldIds
     * @param  array<int, int>  $newIds
     * @return array{ok:bool, message?:string}
     */
    public function syncOneService(object $svc, array $oldIds, array $newIds): array
    {
        if (L2tpProvisionerService::isL2tp($svc)) {
            return ['ok' => true, 'message' => 'skipped'];
        }
        $email = trim((string) ($svc->email ?? ''));
        if ($email === '') {
            return ['ok' => false, 'message' => 'missing_email'];
        }

        $curIds = $this->serviceInboundIds($svc);
        $toAttach = array_values(array_diff($newIds, $curIds));
        $toDetach = array_values(array_diff($curIds, $newIds));
        $panelId = max(1, (int) ($svc->panel_id ?? 1));
        $sid = (int) ($svc->id ?? 0);

        $result = $this->panels->runWithPanel($panelId, function ($client) use ($svc, $email, $toAttach, $toDetach, $newIds, $sid, $panelId) {
            if (! $client->loginWithRetries(6, 300000)) {
                return ['ok' => false, 'message' => 'login'];
            }
            if ($client->isV3ClientsApi()) {
                if ($toAttach !== []) {
                    $att = $client->clientAttachV3($email, $toAttach);
                    if (! $this->responseOk($client, $att)) {
                        return ['ok' => false, 'message' => 'attach_failed'];
                    }
                }
                if ($toDetach !== []) {
                    $det = $client->clientDetachV3($email, $toDetach);
                    if (! $this->responseOk($client, $det)) {
                        return ['ok' => false, 'message' => 'detach_failed'];
                    }
                }
                $sync = $this->xui->runWithPanel($panelId, fn () => $this->xui->syncServiceRowToPanel((array) $svc));
                if (empty($sync['ok'])) {
                    return ['ok' => false, 'message' => (string) ($sync['message'] ?? 'sync_failed')];
                }
            }
            if ($sid > 0) {
                $update = [
                    'inbound_id' => $newIds !== [] ? (int) $newIds[0] : (int) ($svc->inbound_id ?? 0),
                ];
                if (Schema::hasColumn('svp_services', 'inbound_ids')) {
                    $update['inbound_ids'] = json_encode(array_values($newIds));
                }
                DB::table('svp_services')->where('id', $sid)->update($update);
            }

            return ['ok' => true];
        });

        return is_array($result) ? $result : ['ok' => false, 'message' => 'unknown'];
    }

    /** @return array<int, int> */
    protected function serviceInboundIds(object $svc): array
    {
        if (Schema::hasColumn('svp_services', 'inbound_ids')) {
            $raw = $svc->inbound_ids ?? null;
            if (is_string($raw) && $raw !== '') {
                $dec = json_decode($raw, true);
                if (is_array($dec)) {
                    return $this->normalizeIds($dec);
                }
            }
        }

        $iid = (int) ($svc->inbound_id ?? 0);

        return $iid > 0 ? [$iid] : [];
    }

    /** @param  array<int, int>  $ids */
    /** @return array<int, int> */
    protected function normalizeIds(array $ids): array
    {
        $out = [];
        foreach ($ids as $v) {
            $iid = (int) $v;
            if ($iid > 0 && ! in_array($iid, $out, true)) {
                $out[] = $iid;
            }
        }
        sort($out);

        return $out;
    }

    protected function jobKey(int $planId): string
    {
        return 'svp_plan_inb_sync_'.$planId;
    }

    protected function responseOk(mixed $client, mixed $res): bool
    {
        if ($client instanceof XuiClient) {
            return $client->panelJsonMsg($res) === '' || ! empty($res['success']);
        }

        return is_array($res) && ! empty($res['ok']);
    }
}
