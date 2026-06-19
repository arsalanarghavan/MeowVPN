<?php

namespace App\Services\AdminQuery;

use App\Models\DashboardUser;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsersBulkQueryService
{
    public function __construct(protected ResellerScopeService $scope) {}

    /** @return array<string, mixed> */
    public function jobs(int $page, int $perPage, ?DashboardUser $actor): array
    {
        if (! Schema::hasTable('svp_users_bulk_jobs')) {
            return svp_ok([
                'jobs' => [],
                'itemAggregates' => [],
                'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => 0],
            ]);
        }

        $q = DB::table('svp_users_bulk_jobs')->orderByDesc('id');
        if ($actor?->role === 'reseller' && (int) ($actor->svp_user_id ?? 0) > 0) {
            $q->where('created_by_svp_user_id', (int) $actor->svp_user_id);
        }

        $total = (clone $q)->count();
        $rows = $q->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();
        $itemAggregates = [];
        if ($rows !== [] && Schema::hasTable('svp_users_bulk_job_items')) {
            $jobIds = array_map(fn ($r) => (int) $r->id, $rows);
            $stats = DB::table('svp_users_bulk_job_items')
                ->whereIn('job_id', $jobIds)
                ->selectRaw('job_id, status, COUNT(*) as cnt')
                ->groupBy('job_id', 'status')
                ->get();
            foreach ($stats as $sr) {
                $itemAggregates[] = [
                    'jobId' => (int) $sr->job_id,
                    'status' => (string) $sr->status,
                    'count' => (int) $sr->cnt,
                ];
            }
        }

        return svp_ok([
            'jobs' => $rows,
            'itemAggregates' => $itemAggregates,
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
        ]);
    }

    /** @return array<string, mixed> */
    public function jobItems(int $jobId, int $page, int $perPage, ?DashboardUser $actor): array
    {
        if ($jobId < 1 || ! Schema::hasTable('svp_users_bulk_job_items')) {
            return svp_err('not_found');
        }

        if ($actor?->role === 'reseller' && Schema::hasTable('svp_users_bulk_jobs')) {
            $owner = (int) DB::table('svp_users_bulk_jobs')->where('id', $jobId)->value('created_by_svp_user_id');
            if ($owner !== (int) ($actor->svp_user_id ?? 0)) {
                return svp_err('forbidden');
            }
        }

        $q = DB::table('svp_users_bulk_job_items')->where('job_id', $jobId)->orderBy('id');
        $total = (clone $q)->count();
        $rows = $q->offset(($page - 1) * $perPage)->limit($perPage)->get();

        return svp_ok([
            'rows' => $rows,
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
        ]);
    }
}
