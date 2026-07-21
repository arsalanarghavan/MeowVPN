<?php

namespace App\Services\AdminState;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP SimpleVPBot_Admin_Reseller_Reports::build parity (Laravel).
 */
class ResellerReportsBuilder
{
    /** @var list<int> */
    public const ALLOWED_WINDOW_DAYS = [7, 30, 90];

    /**
     * @param  array{page?:int,per_page?:int,offset?:int}  $pagination
     * @return array{
     *   window_days:int,
     *   since:string,
     *   backfill_done:bool,
     *   daily_scoped:bool,
     *   summary:array<string,mixed>,
     *   rows:list<array<string,mixed>>,
     *   daily:list<array<string,mixed>>,
     *   total:int
     * }
     */
    public function build(
        int $windowDays,
        string $searchQ,
        string $sort,
        array $pagination,
        ?int $scopeAncestorId = null,
    ): array {
        $window = in_array($windowDays, self::ALLOWED_WINDOW_DAYS, true) ? $windowDays : 30;
        $since = now()->subDays($window)->utc()->format('Y-m-d H:i:s');
        $sort = in_array($sort, ['sales', 'wholesale', 'downline', 'balance', 'name'], true) ? $sort : 'sales';
        $q = mb_substr(trim($searchQ), 0, 128);
        $off = max(0, (int) ($pagination['offset'] ?? 0));
        $pp = max(1, (int) ($pagination['per_page'] ?? 25));

        if (! Schema::hasTable('svp_users')) {
            return $this->emptyPayload($window, $since);
        }

        $scopeIds = null;
        if ($scopeAncestorId !== null && $scopeAncestorId > 0) {
            $scopeIds = $this->downlineResellerIds($scopeAncestorId);
            if ($scopeIds === []) {
                return $this->emptyPayload($window, $since);
            }
        }

        $base = DB::table('svp_users as u')->where('u.role', 'reseller');
        if (is_array($scopeIds)) {
            $base->whereIn('u.id', $scopeIds);
        }
        if ($q !== '') {
            $like = '%'.$q.'%';
            $base->where(function ($w) use ($like, $q) {
                $w->where('u.username', 'like', $like)
                    ->orWhere('u.first_name', 'like', $like)
                    ->orWhere('u.last_name', 'like', $like);
                if (ctype_digit($q)) {
                    $w->orWhere('u.id', (int) $q);
                }
            });
        }

        $matchIds = (clone $base)->orderBy('u.id')->pluck('u.id')->map(fn ($v) => (int) $v)->filter(fn ($v) => $v > 0)->values()->all();
        $total = count($matchIds);
        if ($total < 1) {
            return $this->emptyPayload($window, $since);
        }

        $ranked = $this->rankResellerIds($matchIds, $sort, $since);
        $pageIds = array_slice($ranked, $off, $pp);
        $maps = $this->aggregateMaps($since, $pageIds);

        $usersById = [];
        if ($pageIds !== []) {
            foreach (DB::table('svp_users')->whereIn('id', $pageIds)->get(['id', 'username', 'first_name', 'last_name', 'status', 'balance']) as $ur) {
                $usersById[(int) $ur->id] = $ur;
            }
        }

        $rows = [];
        foreach ($pageIds as $rid) {
            $ur = $usersById[$rid] ?? null;
            if (! $ur) {
                continue;
            }
            $rows[] = $this->rowFromUserAndMaps($ur, $maps, $rid);
        }

        $dailyScoped = $q !== '';
        $daily = $this->buildDailySeries($since, $window, $matchIds);
        $summary = $this->buildSummary($since, $matchIds);

        return [
            'window_days' => $window,
            'since' => $since,
            'backfill_done' => $this->backfillDone(),
            'daily_scoped' => $dailyScoped,
            'summary' => $summary,
            'rows' => $rows,
            'daily' => $daily,
            'total' => $total,
        ];
    }

    /** @return list<int> */
    public function downlineResellerIds(int $ancestorId): array
    {
        if ($ancestorId < 1 || ! Schema::hasTable('svp_reseller_closure') || ! Schema::hasTable('svp_users')) {
            return [];
        }

        return DB::table('svp_users as u')
            ->join('svp_reseller_closure as c', function ($j) use ($ancestorId) {
                $j->on('c.descendant_id', '=', 'u.id')
                    ->where('c.ancestor_id', $ancestorId)
                    ->where('c.depth', '>', 0);
            })
            ->where('u.role', 'reseller')
            ->orderBy('u.id')
            ->pluck('u.id')
            ->map(fn ($v) => (int) $v)
            ->filter(fn ($v) => $v > 0)
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   window_days:int,
     *   since:string,
     *   backfill_done:bool,
     *   daily_scoped:bool,
     *   summary:array<string,mixed>,
     *   rows:list<array<string,mixed>>,
     *   daily:list<array<string,mixed>>,
     *   total:int
     * }
     */
    protected function emptyPayload(int $window, string $since): array
    {
        return [
            'window_days' => $window,
            'since' => $since,
            'backfill_done' => $this->backfillDone(),
            'daily_scoped' => false,
            'summary' => [
                'reseller_count' => 0,
                'total_sales_toman' => 0.0,
                'total_wholesale_toman' => 0.0,
                'total_receipts_toman' => 0.0,
                'total_downline_users' => 0,
                'margin_est' => 0.0,
                'top_reseller' => [
                    'reseller_id' => 0,
                    'name' => '',
                    'sales_toman' => 0.0,
                ],
            ],
            'rows' => [],
            'daily' => [],
            'total' => 0,
        ];
    }

    protected function backfillDone(): bool
    {
        if (! Schema::hasTable('svp_settings')) {
            return true;
        }
        $keyCol = Schema::hasColumn('svp_settings', 'key_name') ? 'key_name' : 'option_key';
        $valCol = Schema::hasColumn('svp_settings', 'value') ? 'value' : 'option_value';
        $row = DB::table('svp_settings')->where($keyCol, 'reseller_backfill_done')->first();
        if (! $row) {
            return true;
        }
        $v = strtolower(trim((string) ($row->{$valCol} ?? '')));

        return in_array($v, ['1', 'true', 'yes'], true);
    }

    /**
     * @param  list<int>  $matchIds
     * @return list<int>
     */
    protected function rankResellerIds(array $matchIds, string $sort, string $since): array
    {
        if ($matchIds === []) {
            return [];
        }
        if ($sort === 'name') {
            return DB::table('svp_users')
                ->whereIn('id', $matchIds)
                ->orderByRaw("TRIM(CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,''))) ASC")
                ->orderBy('username')
                ->orderBy('id')
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }
        if ($sort === 'balance') {
            return DB::table('svp_users')
                ->whereIn('id', $matchIds)
                ->orderByDesc('balance')
                ->orderByDesc('id')
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        $metrics = array_fill_keys($matchIds, 0.0);
        if ($sort === 'sales' && Schema::hasTable('svp_transactions')) {
            $rows = DB::table('svp_transactions as t')
                ->selectRaw('t.billing_reseller_svp_id as rid, COALESCE(SUM(ABS(t.amount)),0) as v')
                ->whereIn('t.status', ['approved', 'completed'])
                ->whereIn('t.type', ['purchase', 'renew'])
                ->where('t.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $matchIds)
                ->groupBy('rid')
                ->get();
            foreach ($rows as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $metrics[$rid] = (float) ($r->v ?? 0);
                }
            }
        } elseif ($sort === 'wholesale' && Schema::hasTable('svp_reseller_wholesale_accruals')) {
            $rows = DB::table('svp_reseller_wholesale_accruals')
                ->selectRaw('reseller_svp_user_id as rid, COALESCE(SUM(delta_wholesale_toman),0) as v')
                ->where('created_at', '>=', $since)
                ->whereIn('reseller_svp_user_id', $matchIds)
                ->groupBy('rid')
                ->get();
            foreach ($rows as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $metrics[$rid] = (float) ($r->v ?? 0);
                }
            }
        } elseif ($sort === 'downline' && Schema::hasTable('svp_reseller_closure')) {
            $rows = DB::table('svp_reseller_closure')
                ->selectRaw('ancestor_id as rid, COUNT(*) as v')
                ->where('depth', '>', 0)
                ->whereIn('ancestor_id', $matchIds)
                ->groupBy('rid')
                ->get();
            foreach ($rows as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $metrics[$rid] = (float) ($r->v ?? 0);
                }
            }
        }

        uasort($metrics, function ($a, $b) {
            if ($a == $b) {
                return 0;
            }

            return $a < $b ? 1 : -1;
        });

        return array_map('intval', array_keys($metrics));
    }

    /**
     * @param  list<int>  $scopeIds
     * @return array{
     *   downline:array<int,int>,
     *   active_services:array<int,int>,
     *   sales:array<int,array{count:int,toman:float}>,
     *   wholesale:array<int,array{gb:float,toman:float}>,
     *   receipts:array<int,float>
     * }
     */
    protected function aggregateMaps(string $since, array $scopeIds): array
    {
        $out = [
            'downline' => [],
            'active_services' => [],
            'sales' => [],
            'wholesale' => [],
            'receipts' => [],
        ];
        if ($scopeIds === []) {
            return $out;
        }

        if (Schema::hasTable('svp_reseller_closure')) {
            $dl = DB::table('svp_reseller_closure')
                ->selectRaw('ancestor_id as rid, COUNT(*) as cnt')
                ->where('depth', '>', 0)
                ->whereIn('ancestor_id', $scopeIds)
                ->groupBy('rid')
                ->get();
            foreach ($dl as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $out['downline'][$rid] = (int) ($r->cnt ?? 0);
                }
            }

            if (Schema::hasTable('svp_services')) {
                $svc = DB::table('svp_services as s')
                    ->join('svp_reseller_closure as c', function ($j) {
                        $j->on('c.descendant_id', '=', 's.user_id')->where('c.depth', '>', 0);
                    })
                    ->whereNull('s.deleted_at')
                    ->whereIn('c.ancestor_id', $scopeIds)
                    ->selectRaw('c.ancestor_id as rid, SUM(CASE WHEN (s.expires_at IS NULL OR s.expires_at > ?) THEN 1 ELSE 0 END) as active_n', [now()])
                    ->groupBy('c.ancestor_id')
                    ->get();
                foreach ($svc as $r) {
                    $rid = (int) ($r->rid ?? 0);
                    if ($rid > 0) {
                        $out['active_services'][$rid] = (int) ($r->active_n ?? 0);
                    }
                }
            }
        }

        if (Schema::hasTable('svp_transactions')) {
            $sales = DB::table('svp_transactions as t')
                ->selectRaw('t.billing_reseller_svp_id as rid, COUNT(*) as sales_count, COALESCE(SUM(ABS(t.amount)),0) as sales_toman')
                ->whereIn('t.status', ['approved', 'completed'])
                ->whereIn('t.type', ['purchase', 'renew'])
                ->where('t.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $scopeIds)
                ->groupBy('rid')
                ->get();
            foreach ($sales as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $out['sales'][$rid] = [
                        'count' => (int) ($r->sales_count ?? 0),
                        'toman' => round((float) ($r->sales_toman ?? 0), 2),
                    ];
                }
            }
        }

        if (Schema::hasTable('svp_reseller_wholesale_accruals')) {
            $wh = DB::table('svp_reseller_wholesale_accruals')
                ->selectRaw('reseller_svp_user_id as rid, COALESCE(SUM(delta_gb),0) as gb, COALESCE(SUM(delta_wholesale_toman),0) as toman')
                ->where('created_at', '>=', $since)
                ->whereIn('reseller_svp_user_id', $scopeIds)
                ->groupBy('rid')
                ->get();
            foreach ($wh as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $out['wholesale'][$rid] = [
                        'gb' => round((float) ($r->gb ?? 0), 2),
                        'toman' => round((float) ($r->toman ?? 0), 2),
                    ];
                }
            }
        }

        if (Schema::hasTable('svp_receipts') && Schema::hasTable('svp_transactions')) {
            $rcpt = DB::table('svp_receipts as r')
                ->join('svp_transactions as t', 't.id', '=', 'r.transaction_id')
                ->selectRaw('t.billing_reseller_svp_id as rid, COALESCE(SUM(r.amount),0) as toman')
                ->where('r.status', 'approved')
                ->whereIn('t.status', ['approved', 'completed'])
                ->where('r.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $scopeIds)
                ->groupBy('rid')
                ->get();
            foreach ($rcpt as $r) {
                $rid = (int) ($r->rid ?? 0);
                if ($rid > 0) {
                    $out['receipts'][$rid] = round((float) ($r->toman ?? 0), 2);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $maps
     * @return array<string, mixed>
     */
    protected function rowFromUserAndMaps(object $ur, array $maps, int $rid): array
    {
        $sales = $maps['sales'][$rid] ?? ['count' => 0, 'toman' => 0.0];
        $wh = $maps['wholesale'][$rid] ?? ['gb' => 0.0, 'toman' => 0.0];
        $salesToman = (float) ($sales['toman'] ?? 0);
        $wholesaleToman = (float) ($wh['toman'] ?? 0);

        return [
            'reseller_id' => $rid,
            'username' => (string) ($ur->username ?? ''),
            'first_name' => (string) ($ur->first_name ?? ''),
            'last_name' => (string) ($ur->last_name ?? ''),
            'status' => (string) ($ur->status ?? ''),
            'balance' => round((float) ($ur->balance ?? 0), 2),
            'downline_users' => (int) ($maps['downline'][$rid] ?? 0),
            'active_services' => (int) ($maps['active_services'][$rid] ?? 0),
            'sales_count' => (int) ($sales['count'] ?? 0),
            'sales_toman' => $salesToman,
            'wholesale_gb' => round((float) ($wh['gb'] ?? 0), 2),
            'wholesale_toman' => $wholesaleToman,
            'receipts_toman' => (float) ($maps['receipts'][$rid] ?? 0),
            'margin_est' => round($salesToman - $wholesaleToman, 2),
        ];
    }

    /**
     * @param  list<int>  $resellerIds
     * @return list<array{date:string,sales_toman:float,wholesale_toman:float}>
     */
    protected function buildDailySeries(string $since, int $windowDays, array $resellerIds): array
    {
        $scopeIds = array_values(array_filter(array_map('intval', $resellerIds), fn (int $v) => $v > 0));
        if ($scopeIds === []) {
            return [];
        }

        $dailySales = [];
        $dailyWholesale = [];

        if (Schema::hasTable('svp_transactions')) {
            $rows = DB::table('svp_transactions as t')
                ->selectRaw('DATE(t.created_at) as d, COALESCE(SUM(ABS(t.amount)),0) as sales_toman')
                ->whereIn('t.status', ['approved', 'completed'])
                ->whereIn('t.type', ['purchase', 'renew'])
                ->where('t.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $scopeIds)
                ->where('t.billing_reseller_svp_id', '>', 0)
                ->groupBy('d')
                ->orderBy('d')
                ->get();
            foreach ($rows as $r) {
                $d = (string) ($r->d ?? '');
                if ($d !== '') {
                    $dailySales[$d] = round((float) ($r->sales_toman ?? 0), 2);
                }
            }
        }

        if (Schema::hasTable('svp_reseller_wholesale_accruals')) {
            $rows = DB::table('svp_reseller_wholesale_accruals')
                ->selectRaw('DATE(created_at) as d, COALESCE(SUM(delta_wholesale_toman),0) as wholesale_toman')
                ->where('created_at', '>=', $since)
                ->whereIn('reseller_svp_user_id', $scopeIds)
                ->groupBy('d')
                ->orderBy('d')
                ->get();
            foreach ($rows as $r) {
                $d = (string) ($r->d ?? '');
                if ($d !== '') {
                    $dailyWholesale[$d] = round((float) ($r->wholesale_toman ?? 0), 2);
                }
            }
        }

        $out = [];
        $start = strtotime($since.' UTC');
        $end = time();
        if (! $start || $start >= $end) {
            return $out;
        }
        $days = max(1, min(90, $windowDays));
        for ($i = 0; $i <= $days; $i++) {
            $ts = $start + ($i * 86400);
            if ($ts > $end) {
                break;
            }
            $d = gmdate('Y-m-d', $ts);
            $out[] = [
                'date' => $d,
                'sales_toman' => (float) ($dailySales[$d] ?? 0),
                'wholesale_toman' => (float) ($dailyWholesale[$d] ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $resellerIds
     * @return array<string, mixed>
     */
    protected function buildSummary(string $since, array $resellerIds): array
    {
        $empty = [
            'reseller_count' => 0,
            'total_sales_toman' => 0.0,
            'total_wholesale_toman' => 0.0,
            'total_receipts_toman' => 0.0,
            'total_downline_users' => 0,
            'margin_est' => 0.0,
            'top_reseller' => [
                'reseller_id' => 0,
                'name' => '',
                'sales_toman' => 0.0,
            ],
        ];
        if ($resellerIds === []) {
            return $empty;
        }

        $totalSales = 0.0;
        $topId = 0;
        $topSales = 0.0;
        if (Schema::hasTable('svp_transactions')) {
            $totalSales = (float) DB::table('svp_transactions as t')
                ->whereIn('t.status', ['approved', 'completed'])
                ->whereIn('t.type', ['purchase', 'renew'])
                ->where('t.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $resellerIds)
                ->sum(DB::raw('ABS(t.amount)'));
            $top = DB::table('svp_transactions as t')
                ->selectRaw('t.billing_reseller_svp_id as rid, COALESCE(SUM(ABS(t.amount)),0) as sales_toman')
                ->whereIn('t.status', ['approved', 'completed'])
                ->whereIn('t.type', ['purchase', 'renew'])
                ->where('t.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $resellerIds)
                ->groupBy('rid')
                ->orderByDesc('sales_toman')
                ->orderByDesc('rid')
                ->first();
            if ($top) {
                $topId = (int) ($top->rid ?? 0);
                $topSales = (float) ($top->sales_toman ?? 0);
            }
        }

        $totalWholesale = 0.0;
        if (Schema::hasTable('svp_reseller_wholesale_accruals')) {
            $totalWholesale = (float) DB::table('svp_reseller_wholesale_accruals')
                ->where('created_at', '>=', $since)
                ->whereIn('reseller_svp_user_id', $resellerIds)
                ->sum('delta_wholesale_toman');
        }

        $totalReceipts = 0.0;
        if (Schema::hasTable('svp_receipts') && Schema::hasTable('svp_transactions')) {
            $totalReceipts = (float) DB::table('svp_receipts as r')
                ->join('svp_transactions as t', 't.id', '=', 'r.transaction_id')
                ->where('r.status', 'approved')
                ->whereIn('t.status', ['approved', 'completed'])
                ->where('r.created_at', '>=', $since)
                ->whereIn('t.billing_reseller_svp_id', $resellerIds)
                ->sum('r.amount');
        }

        $totalDownline = 0;
        if (Schema::hasTable('svp_reseller_closure')) {
            $totalDownline = (int) DB::table('svp_reseller_closure')
                ->where('depth', '>', 0)
                ->whereIn('ancestor_id', $resellerIds)
                ->count();
        }

        $topName = '';
        if ($topId > 0) {
            $u = DB::table('svp_users')->where('id', $topId)->first(['username', 'first_name', 'last_name']);
            if ($u) {
                $name = trim(((string) ($u->first_name ?? '')).' '.((string) ($u->last_name ?? '')));
                $topName = $name !== '' ? $name : (string) ($u->username ?? '');
            }
        }

        return [
            'reseller_count' => count($resellerIds),
            'total_sales_toman' => round($totalSales, 2),
            'total_wholesale_toman' => round($totalWholesale, 2),
            'total_receipts_toman' => round($totalReceipts, 2),
            'total_downline_users' => $totalDownline,
            'margin_est' => round($totalSales - $totalWholesale, 2),
            'top_reseller' => [
                'reseller_id' => $topId,
                'name' => $topName,
                'sales_toman' => round($topSales, 2),
            ],
        ];
    }
}
