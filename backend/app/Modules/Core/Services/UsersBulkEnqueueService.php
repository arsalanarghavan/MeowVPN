<?php

namespace App\Modules\Core\Services;

use App\Models\SvpUser;
use Illuminate\Contracts\Auth\Authenticatable;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\Facades\DB;

class UsersBulkEnqueueService
{
    public function __construct(protected ResellerScopeService $scope) {}

    /** @param  array<string, mixed>  $payload */
    public function enqueueJob(string $operation, array $payload, ?Authenticatable $actor): array
    {
        $actorSvp = (int) ($actor?->svp_user_id ?? 0);
        $scope = (string) ($payload['scope'] ?? 'all_approved');

        if (! empty($payload['dry_run'])) {
            $ids = $this->resolveUserIds($scope, $payload, $actorSvp);

            return svp_ok(['preview' => ['user_count' => count($ids)]]);
        }

        $ids = $this->resolveUserIds($scope, $payload, $actorSvp);
        if ($ids === [] && $scope !== 'panel_active_clients') {
            return svp_err('empty_scope');
        }

        $jobPayload = array_merge($payload, $this->payloadBase($payload, $actorSvp));

        $jobId = (int) DB::table('svp_users_bulk_jobs')->insertGetId([
            'operation' => $operation,
            'scope' => $scope,
            'payload_json' => json_encode($jobPayload, JSON_UNESCAPED_UNICODE),
            'status' => 'pending',
            'created_by_svp_user_id' => $actorSvp,
            'created_at' => now(),
        ]);

        $this->enqueueUserItems($jobId, $ids);

        return svp_ok(['job_id' => $jobId, 'queued' => count($ids)]);
    }

    /** @param  array<string, mixed>  $payload
     * @return array<int, int>
     */
    public function resolveUserIds(string $scope, array $payload, int $actorSvp): array
    {
        $scopeIds = $actorSvp > 0 ? $this->scope->moderatableUserIds($actorSvp) : null;

        if ($scope === 'custom_ids') {
            $raw = isset($payload['user_ids']) && is_array($payload['user_ids']) ? $payload['user_ids'] : [];
            $ids = array_values(array_unique(array_filter(array_map('intval', $raw), fn ($v) => $v > 0)));
            if (count($ids) > 500) {
                return [];
            }
            if ($scopeIds !== null) {
                $allowed = array_flip($scopeIds);
                $ids = array_values(array_filter($ids, fn ($id) => isset($allowed[$id])));
            }
            if ($ids !== []) {
                $ids = SvpUser::query()
                    ->whereIn('id', $ids)
                    ->where('status', 'approved')
                    ->where('role', '!=', 'reseller')
                    ->pluck('id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
            }

            return $this->finalizeUserIds($ids, $payload);
        }

        if ($scope === 'all_approved') {
            $q = SvpUser::query()
                ->where('status', 'approved')
                ->where('role', '!=', 'reseller')
                ->orderBy('id')
                ->limit(500);
            if ($scopeIds !== null) {
                $q->whereIn('id', $scopeIds);
            }

            return $this->finalizeUserIds($q->pluck('id')->map(fn ($v) => (int) $v)->all(), $payload);
        }

        if ($scope === 'approved_with_active_service') {
            $q = DB::table('svp_users as u')
                ->join('svp_services as s', function ($join) {
                    $join->on('s.user_id', '=', 'u.id')
                        ->whereNull('s.deleted_at')
                        ->where(function ($q2) {
                            $q2->whereNull('s.expires_at')->orWhere('s.expires_at', '>', now());
                        });
                })
                ->where('u.status', 'approved')
                ->where('u.role', '!=', 'reseller')
                ->distinct()
                ->orderBy('u.id')
                ->limit(500)
                ->pluck('u.id')
                ->map(fn ($v) => (int) $v)
                ->all();
            if ($scopeIds !== null) {
                $allowed = array_flip($scopeIds);
                $q = array_values(array_filter($q, fn ($id) => isset($allowed[$id])));
            }

            return $this->finalizeUserIds($q, $payload);
        }

        return [];
    }

    /** @param  array<int, int>  $ids
     * @return array<int, int>
     */
    protected function finalizeUserIds(array $ids, array $payload): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return $ids;
        }
        $inboundId = (int) ($payload['inbound_id'] ?? 0);
        $q = DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('panel_id', $panelId)
            ->whereIn('user_id', $ids);
        if ($inboundId > 0) {
            $q->where('inbound_id', $inboundId);
        }

        return array_values(array_unique($q->pluck('user_id')->map(fn ($v) => (int) $v)->all()));
    }

    /** @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function payloadBase(array $payload, int $actorSvp): array
    {
        $out = [
            'panel_id' => (int) ($payload['panel_id'] ?? 0),
            'inbound_id' => (int) ($payload['inbound_id'] ?? 0),
        ];
        if ($actorSvp > 0) {
            $out['__actor_svp_user_id'] = $actorSvp;
        }

        return $out;
    }

    /** @param  array<int, int>  $userIds */
    protected function enqueueUserItems(int $jobId, array $userIds): void
    {
        $rows = [];
        foreach ($userIds as $uid) {
            if ($uid < 1) {
                continue;
            }
            $rows[] = [
                'job_id' => $jobId,
                'user_id' => $uid,
                'panel_id' => 0,
                'inbound_id' => 0,
                'client_email' => '',
                'status' => 'pending',
                'tries' => 0,
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('svp_users_bulk_job_items')->insert($chunk);
        }
    }
}
