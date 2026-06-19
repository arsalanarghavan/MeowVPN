<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class ResellerReportsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsResellerReports();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $p = $ctx->page('resellerReports');
        $rows = [];
        $total = 0;

        if ($this->tableExists('svp_transactions')) {
            $q = DB::table('svp_transactions as t')
                ->where('t.billing_reseller_svp_id', '>', 0)
                ->orderByDesc('t.id');
            if ($ctx->moderatableUserIds !== []) {
                $q->whereIn('t.user_id', $ctx->moderatableUserIds);
            }
            $total = (clone $q)->count();
            $rows = (clone $q)->offset($p['offset'])->limit($p['per_page'])->get()->map(fn ($r) => (array) $r)->all();
        }

        $daily = [];
        if ($this->tableExists('svp_transactions')) {
            $dailyQ = DB::table('svp_transactions as t')
                ->selectRaw('DATE(t.created_at) as day, COUNT(*) as cnt, COALESCE(SUM(t.amount),0) as amount')
                ->where('t.billing_reseller_svp_id', '>', 0)
                ->where('t.created_at', '>=', now()->subDays(30))
                ->groupBy('day')
                ->orderBy('day');
            if ($ctx->moderatableUserIds !== []) {
                $dailyQ->whereIn('t.user_id', $ctx->moderatableUserIds);
            }
            $daily = $dailyQ->get()->map(fn ($r) => [
                'day' => (string) $r->day,
                'count' => (int) $r->cnt,
                'amount' => (float) $r->amount,
            ])->all();
        }

        $result->setTotal('resellerReports', $total);
        $result->merge([
            'resellerReportsRows' => $rows,
            'resellerReportsStats' => [
                'rows_total' => $total,
                'amount_total' => array_sum(array_map(fn ($r) => (float) ($r['amount'] ?? 0), $rows)),
            ],
            'resellerReportsDaily' => $daily,
        ]);
    }
}
