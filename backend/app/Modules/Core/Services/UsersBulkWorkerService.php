<?php

namespace App\Modules\Core\Services;

use App\Models\SvpUser;
use App\Modules\Core\Mutations\UserMutations;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\Commerce\ServiceProvisionService;
use Illuminate\Support\Facades\DB;

class UsersBulkWorkerService
{
    public function __construct(
        protected ResellerScopeService $scope,
        protected ServiceProvisionService $provision,
        protected UserMutations $userMutations,
        protected UserBotNotifyService $notify,
    ) {}

    public function runBatch(int $batchSize = 20): void
    {
        $items = $this->popPendingItems($batchSize);
        foreach ($items as $it) {
            $itemId = (int) ($it['id'] ?? 0);
            $jobId = (int) ($it['job_id'] ?? 0);
            $tries = (int) ($it['tries'] ?? 0) + 1;
            $r = $this->runOneItem($it);
            if (! empty($r['ok'])) {
                DB::table('svp_users_bulk_job_items')->where('id', $itemId)->update([
                    'status' => 'success',
                    'tries' => $tries,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);
            } else {
                DB::table('svp_users_bulk_job_items')->where('id', $itemId)->update([
                    'status' => 'failed',
                    'tries' => $tries,
                    'last_error' => (string) ($r['reason'] ?? 'failed'),
                    'updated_at' => now(),
                ]);
            }
            $this->maybeMarkJobDone($jobId);
        }
    }

    /** @param  array<string, mixed>  $item
     * @return array{ok:bool, reason?:string}
     */
    protected function runOneItem(array $item): array
    {
        if ((int) ($item['panel_id'] ?? 0) > 0) {
            return ['ok' => false, 'reason' => 'panel_ops_deferred'];
        }

        $jobId = (int) ($item['job_id'] ?? 0);
        $userId = (int) ($item['user_id'] ?? 0);
        if ($jobId < 1 || $userId < 1) {
            return ['ok' => false, 'reason' => 'invalid_item'];
        }

        $job = DB::table('svp_users_bulk_jobs')->where('id', $jobId)->first();
        if (! $job) {
            return ['ok' => false, 'reason' => 'missing_job'];
        }
        if ((string) $job->status === 'cancelled') {
            return ['ok' => false, 'reason' => 'job_cancelled'];
        }

        $payload = json_decode((string) ($job->payload_json ?? '{}'), true);
        if (! is_array($payload)) {
            $payload = [];
        }

        $actor = (int) ($payload['__actor_svp_user_id'] ?? 0);
        if ($actor > 0 && ! $this->scope->resellerMayModerateUser($actor, $userId)) {
            return ['ok' => false, 'reason' => 'forbidden_scope'];
        }

        $op = (string) $job->operation;

        if ($op === 'wallet') {
            $delta = isset($payload['delta']) ? (float) $payload['delta'] : 0.0;
            $res = $this->userMutations->userBalanceDelta([
                'user_id' => $userId,
                'delta' => $delta,
            ], null);

            return ! empty($res['ok']) ? ['ok' => true] : ['ok' => false, 'reason' => (string) ($res['message'] ?? 'failed')];
        }

        $activeOnly = in_array($op, ['volume', 'extend'], true);
        $svcIds = $this->serviceIdsForUser($userId, $payload, $activeOnly);
        if ($svcIds === []) {
            return ['ok' => true];
        }

        $ok = 0;
        $fail = 0;
        foreach ($svcIds as $sid) {
            $r = $this->runServiceOp($op, $sid, $payload);
            if (! empty($r['ok'])) {
                ++$ok;
            } else {
                ++$fail;
            }
        }

        if ($fail > 0 && $ok < 1) {
            return ['ok' => false, 'reason' => 'all_service_actions_failed'];
        }

        if (in_array($op, ['volume', 'extend', 'slots'], true) && $ok > 0) {
            $this->maybeNotifyServiceOp($userId, $op, $payload);
        }

        return ['ok' => true];
    }

    /** @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    protected function serviceIdsForUser(int $userId, array $payload, bool $activeOnly): array
    {
        $q = DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('user_id', $userId);
        if ($activeOnly) {
            $q->where(function ($sub) {
                $sub->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
        }
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId > 0) {
            $q->where('panel_id', $panelId);
            $inboundId = (int) ($payload['inbound_id'] ?? 0);
            if ($inboundId > 0) {
                $q->where('inbound_id', $inboundId);
            }
        }

        return $q->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /** @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function runServiceOp(string $op, int $serviceId, array $payload): array
    {
        $reduce = ! empty($payload['reduce']);

        return match ($op) {
            'volume' => $reduce
                ? $this->provision->reduceVolume($serviceId, max(1, (int) ($payload['extra_gb'] ?? 1)))
                : $this->provision->addVolume($serviceId, max(1, (int) ($payload['extra_gb'] ?? 1)), 'free'),
            'extend' => $reduce
                ? $this->provision->reduceDays($serviceId, max(1, (int) ($payload['days'] ?? 1)))
                : $this->provision->addDays($serviceId, max(1, (int) ($payload['days'] ?? 1))),
            'slots' => $this->runSlotsOp($serviceId, $payload),
            'alerts' => $this->runAlertsOp($serviceId, $payload),
            default => svp_err('unsupported_op'),
        };
    }

    /** @param  array<string, mixed>  $payload */
    protected function runSlotsOp(int $serviceId, array $payload): array
    {
        $n = max(1, (int) ($payload['extra_users'] ?? 1));
        if (! empty($payload['reduce'])) {
            DB::table('svp_services')->where('id', $serviceId)->decrement('client_slots', $n);
        } else {
            DB::table('svp_services')->where('id', $serviceId)->increment('client_slots', $n);
        }

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    protected function runAlertsOp(int $serviceId, array $payload): array
    {
        $row = DB::table('svp_services')->where('id', $serviceId)->first();
        if (! $row) {
            return svp_err('not_found');
        }
        $alerts = json_decode((string) ($row->alerts_json ?? '{}'), true);
        if (! is_array($alerts)) {
            $alerts = [];
        }
        foreach (['alerts_enabled', 'alerts_volume', 'alerts_expiry', 'alerts_users'] as $k) {
            if (array_key_exists($k, $payload)) {
                $alerts[$k] = ! empty($payload[$k]);
            }
        }
        DB::table('svp_services')->where('id', $serviceId)->update([
            'alerts_json' => json_encode($alerts),
        ]);

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    protected function maybeNotifyServiceOp(int $userId, string $op, array $payload): void
    {
        if (empty($payload['notify']) || ! empty($payload['reduce'])) {
            return;
        }
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return;
        }
        $name = trim((string) ($user->first_name ?? '')) ?: (string) ($user->username ?? 'کاربر');
        $body = trim((string) ($payload['notify_message'] ?? ''));
        if ($body === '') {
            $gb = max(1, (int) ($payload['extra_gb'] ?? 1));
            $days = max(1, (int) ($payload['days'] ?? 1));
            $body = match ($op) {
                'volume' => $name.' — '.$gb.' GB added to active services.',
                'extend' => $name.' — '.$days.' days added to active services.',
                'slots' => $name.' — concurrent user limit increased on active services.',
                default => '',
            };
        } else {
            $body = str_replace(
                ['{name}', '{extra_gb}', '{days}', '{extra_users}'],
                [
                    $name,
                    (string) max(1, (int) ($payload['extra_gb'] ?? 1)),
                    (string) max(1, (int) ($payload['days'] ?? 1)),
                    (string) max(1, (int) ($payload['extra_users'] ?? 1)),
                ],
                $body
            );
        }
        if ($body !== '') {
            $this->notify->sendToUser($user, $body);
        }
    }

    /** @return array<int, array<string, mixed>> */
    protected function popPendingItems(int $limit): array
    {
        $limit = max(1, min(100, $limit));
        $token = 'u_'.bin2hex(random_bytes(8));

        DB::table('svp_users_bulk_job_items')
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->update(['status' => $token]);

        $rows = DB::table('svp_users_bulk_job_items')
            ->where('status', $token)
            ->orderBy('id')
            ->get()
            ->map(fn ($r) => (array) $r)
            ->all();

        if ($rows === []) {
            return [];
        }

        DB::table('svp_users_bulk_job_items')
            ->where('status', $token)
            ->update(['status' => 'processing', 'updated_at' => now()]);

        return $rows;
    }

    protected function maybeMarkJobDone(int $jobId): void
    {
        $pending = DB::table('svp_users_bulk_job_items')
            ->where('job_id', $jobId)
            ->whereIn('status', ['pending', 'processing'])
            ->count();

        if ($pending > 0) {
            DB::table('svp_users_bulk_jobs')->where('id', $jobId)->update(['status' => 'processing']);

            return;
        }

        $failed = DB::table('svp_users_bulk_job_items')
            ->where('job_id', $jobId)
            ->where('status', 'failed')
            ->count();

        DB::table('svp_users_bulk_jobs')->where('id', $jobId)->update([
            'status' => $failed > 0 ? 'failed' : 'done',
            'finished_at' => now(),
        ]);
    }
}
