<?php

namespace App\Services\Marketing;

use App\Models\SvpMarketingOffer;
use App\Models\SvpMarketingRule;
use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP-shaped marketing lifecycle KPIs (SimpleVPBot_Marketing_Lifecycle_Analytics).
 */
class MarketingLifecycleAnalyticsService
{
    /** @var list<int> */
    public const ALLOWED_WINDOW_DAYS = [7, 30, 90];

    public function __construct(protected MarketingGuardService $guard) {}

    public function normalizeWindowDays(int $raw): int
    {
        return in_array($raw, self::ALLOWED_WINDOW_DAYS, true) ? $raw : 30;
    }

    /**
     * @return array<string, mixed>
     */
    public function buildDashboardPayload(int $windowDays, int $ownerSvpUserId = 0, bool $siteAdminListRules = true): array
    {
        $window = $this->normalizeWindowDays($windowDays);
        $since = now()->subDays($window);
        $owner = max(0, $ownerSvpUserId);
        $scopeOwner = (! $siteAdminListRules || $owner > 0) ? $owner : 0;

        $offerStats = $this->offerStats($since, $scopeOwner);
        $funnel = $this->funnelDaily($since, $owner);
        $ruleRows = $this->listRules($owner, $siteAdminListRules);

        return [
            'window_days' => $window,
            'since' => $since->utc()->format('Y-m-d H:i:s'),
            'summary' => array_merge($offerStats, [
                'eligible_now' => $this->eligibleApprovedCount($owner),
                'retention_rate' => $this->retentionRate($since, $owner),
                'new_to_paid_rate' => $this->newToPaidRate($since, $owner),
                'lifecycle_confirmed' => $this->guard->lifecycleConfirmed(),
                'segment_counts' => $this->segmentCounts($scopeOwner),
                // Back-compat keys used by older stubs / UI paths.
                'sent' => (int) ($offerStats['sent_count'] ?? 0),
                'converted' => (int) ($offerStats['converted_count'] ?? 0),
                'revenue_toman' => (float) ($offerStats['campaign_revenue_toman'] ?? 0),
            ]),
            'funnel' => $funnel,
            'segment_trend' => $this->segmentTrend($since, $scopeOwner),
            'rules' => $ruleRows,
            'rule_stats' => $this->perRuleStats($since, $ruleRows, $owner),
            'health' => $this->healthPayload($ruleRows),
            'skip_breakdown' => $this->skipBreakdown($since, $scopeOwner),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function offerStats(mixed $since, int $ownerId): array
    {
        if (! Schema::hasTable('svp_marketing_offers') || ! Schema::hasTable('svp_marketing_rules')) {
            return [
                'offers_sent' => 0,
                'offers_converted' => 0,
                'sent_count' => 0,
                'converted_count' => 0,
                'offer_success_rate' => 0.0,
                'abandoned_recovery_rate' => 0.0,
                'campaign_revenue_toman' => 0.0,
            ];
        }

        $base = DB::table('svp_marketing_offers as o')
            ->join('svp_marketing_rules as r', 'r.id', '=', 'o.rule_id')
            ->where('o.sent_at', '>=', $since);
        if ($ownerId > 0) {
            $base->where('r.owner_svp_user_id', $ownerId);
        }

        $sent = (int) (clone $base)->whereIn('o.status', ['sent', 'converted'])->count();
        $conv = (int) (clone $base)->where('o.status', 'converted')->count();

        $revenue = 0.0;
        if (Schema::hasTable('svp_transactions')) {
            // WP uses ABS(amount); support both approved and completed statuses used in MeowVPN.
            $revenue = (float) DB::table('svp_transactions as t')
                ->join('svp_marketing_offers as o', 'o.converted_transaction_id', '=', 't.id')
                ->join('svp_marketing_rules as r', 'r.id', '=', 'o.rule_id')
                ->where('o.status', 'converted')
                ->where('o.sent_at', '>=', $since)
                ->when($ownerId > 0, fn ($q) => $q->where('r.owner_svp_user_id', $ownerId))
                ->whereIn('t.status', ['approved', 'completed'])
                ->sum(DB::raw('ABS(t.amount)'));
        }

        $abBase = DB::table('svp_marketing_offers as o')
            ->join('svp_marketing_rules as r', 'r.id', '=', 'o.rule_id')
            ->where('r.segment_key', 'abandoned_checkout')
            ->where('o.sent_at', '>=', $since);
        if ($ownerId > 0) {
            $abBase->where('r.owner_svp_user_id', $ownerId);
        }
        $abSent = (int) (clone $abBase)->whereIn('o.status', ['sent', 'converted'])->count();
        $abConv = (int) (clone $abBase)->where('o.status', 'converted')->count();

        return [
            'offers_sent' => $sent,
            'offers_converted' => $conv,
            'sent_count' => $sent,
            'converted_count' => $conv,
            'offer_success_rate' => $sent > 0 ? round(($conv / $sent) * 100, 2) : 0.0,
            'abandoned_recovery_rate' => $abSent > 0 ? round(($abConv / $abSent) * 100, 2) : 0.0,
            'campaign_revenue_toman' => round($revenue, 2),
        ];
    }

    /**
     * @return list<array{date:string,registered:int,first_pending:int,first_paid:int}>
     */
    protected function funnelDaily(mixed $since, int $ownerId): array
    {
        $days = [];
        $cursor = $since->copy()->startOfDay();
        $end = now()->startOfDay();
        while ($cursor->lte($end)) {
            $d = $cursor->format('Y-m-d');
            $days[$d] = [
                'date' => $d,
                'registered' => 0,
                'first_pending' => 0,
                'first_paid' => 0,
            ];
            $cursor->addDay();
        }

        if (! Schema::hasTable('svp_users')) {
            return array_values($days);
        }

        $userScopeIds = $ownerId > 0 ? $this->moderatableUserIds($ownerId) : null;

        $regQ = SvpUser::query()
            ->where('status', 'approved')
            ->where('created_at', '>=', $since);
        if (is_array($userScopeIds)) {
            if ($userScopeIds === []) {
                return array_values($days);
            }
            $regQ->whereIn('id', $userScopeIds);
        }
        foreach ($regQ->get(['id', 'created_at']) as $row) {
            $d = substr((string) $row->created_at, 0, 10);
            if (isset($days[$d])) {
                $days[$d]['registered']++;
            }
        }

        if (! Schema::hasTable('svp_transactions')) {
            return array_values($days);
        }

        $pending = DB::table('svp_transactions as t')
            ->where('t.status', 'pending')
            ->where('t.type', 'purchase')
            ->where('t.created_at', '>=', $since)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t0')
                    ->whereColumn('t0.user_id', 't.user_id')
                    ->where('t0.type', 'purchase')
                    ->whereColumn('t0.created_at', '<', 't.created_at');
            })
            ->when(is_array($userScopeIds), fn ($q) => $q->whereIn('t.user_id', $userScopeIds ?: [0]))
            ->get(['t.user_id', 't.created_at']);
        foreach ($pending as $row) {
            $d = substr((string) $row->created_at, 0, 10);
            if (isset($days[$d])) {
                $days[$d]['first_pending']++;
            }
        }

        $paid = DB::table('svp_transactions as t')
            ->whereIn('t.status', ['approved', 'completed'])
            ->whereIn('t.type', ['purchase', 'renew', 'service_renew'])
            ->where('t.created_at', '>=', $since)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t0')
                    ->whereColumn('t0.user_id', 't.user_id')
                    ->whereIn('t0.status', ['approved', 'completed'])
                    ->whereIn('t0.type', ['purchase', 'renew', 'service_renew'])
                    ->whereColumn('t0.created_at', '<', 't.created_at');
            })
            ->when(is_array($userScopeIds), fn ($q) => $q->whereIn('t.user_id', $userScopeIds ?: [0]))
            ->get(['t.user_id', 't.created_at']);
        foreach ($paid as $row) {
            $d = substr((string) $row->created_at, 0, 10);
            if (isset($days[$d])) {
                $days[$d]['first_paid']++;
            }
        }

        return array_values($days);
    }

    /**
     * @return list<array{reason:string,count:int}>
     */
    protected function skipBreakdown(mixed $since, int $ownerId): array
    {
        if (! Schema::hasTable('svp_marketing_offers') || ! Schema::hasTable('svp_marketing_rules')) {
            return [];
        }

        $q = DB::table('svp_marketing_offers as o')
            ->join('svp_marketing_rules as r', 'r.id', '=', 'o.rule_id')
            ->where('o.status', 'skipped')
            ->where('o.created_at', '>=', $since)
            ->select('o.meta_json');
        if ($ownerId > 0) {
            $q->where('r.owner_svp_user_id', $ownerId);
        }

        $counts = [];
        foreach ($q->get() as $row) {
            $meta = json_decode((string) ($row->meta_json ?? '{}'), true);
            $reason = is_array($meta) ? (string) ($meta['skip_reason'] ?? 'unknown') : 'unknown';
            if ($reason === '') {
                $reason = 'unknown';
            }
            $counts[$reason] = ($counts[$reason] ?? 0) + 1;
        }

        $out = [];
        foreach ($counts as $reason => $count) {
            $out[] = ['reason' => (string) $reason, 'count' => (int) $count];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $ruleRows
     * @return list<array<string, mixed>>
     */
    protected function perRuleStats(mixed $since, array $ruleRows, int $ownerId): array
    {
        $out = [];
        foreach ($ruleRows as $rule) {
            $rid = (int) ($rule['id'] ?? 0);
            if ($rid < 1) {
                continue;
            }
            $sent = SvpMarketingOffer::query()
                ->where('rule_id', $rid)
                ->whereIn('status', ['sent', 'converted'])
                ->where('sent_at', '>=', $since)
                ->count();
            $conv = SvpMarketingOffer::query()
                ->where('rule_id', $rid)
                ->where('status', 'converted')
                ->where('sent_at', '>=', $since)
                ->count();
            $revenue = 0.0;
            if (Schema::hasTable('svp_transactions')) {
                $txIds = SvpMarketingOffer::query()
                    ->where('rule_id', $rid)
                    ->where('status', 'converted')
                    ->where('sent_at', '>=', $since)
                    ->where('converted_transaction_id', '>', 0)
                    ->pluck('converted_transaction_id')
                    ->map(fn ($v) => (int) $v)
                    ->all();
                if ($txIds !== []) {
                    $revenue = (float) DB::table('svp_transactions')
                        ->whereIn('id', $txIds)
                        ->whereIn('status', ['approved', 'completed'])
                        ->sum(DB::raw('ABS(amount)'));
                }
            }
            $out[] = [
                'rule_id' => $rid,
                'ruleId' => $rid,
                'segment_key' => (string) ($rule['segment_key'] ?? ''),
                'sent' => $sent,
                'converted' => $conv,
                'success_rate' => $sent > 0 ? round(($conv / $sent) * 100, 2) : 0.0,
                'revenue_toman' => round($revenue, 2),
                'eligible_now' => count(app(\App\Modules\Marketing\Services\MarketingSegmentService::class)
                    ->eligibleUserIdsForRule($rule, $ownerId, 500)),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    protected function segmentCounts(int $ownerId): array
    {
        $svc = app(\App\Modules\Marketing\Services\MarketingSegmentService::class);
        $out = [];
        foreach (\App\Modules\Marketing\Services\MarketingSegmentService::SEGMENT_KEYS as $seg) {
            $out[$seg] = count($svc->eligibleUserIdsForRule(['segment_key' => $seg], $ownerId, 500));
        }

        return $out;
    }

    /**
     * @return list<array{date:string,segment:string,sent:int,converted:int}>
     */
    protected function segmentTrend(mixed $since, int $ownerId): array
    {
        if (! Schema::hasTable('svp_marketing_offers') || ! Schema::hasTable('svp_marketing_rules')) {
            return [];
        }
        $q = DB::table('svp_marketing_offers as o')
            ->join('svp_marketing_rules as r', 'r.id', '=', 'o.rule_id')
            ->whereNotNull('o.sent_at')
            ->where('o.sent_at', '>=', $since)
            ->when($ownerId > 0, fn ($qq) => $qq->where('r.owner_svp_user_id', $ownerId))
            ->selectRaw("DATE(o.sent_at) as d, r.segment_key as seg,
                SUM(CASE WHEN o.status IN ('sent','converted') THEN 1 ELSE 0 END) as sent,
                SUM(CASE WHEN o.status='converted' THEN 1 ELSE 0 END) as converted")
            ->groupBy('d', 'seg')
            ->orderBy('d')
            ->limit(500)
            ->get();
        $out = [];
        foreach ($q as $row) {
            $out[] = [
                'date' => (string) ($row->d ?? ''),
                'segment' => (string) ($row->seg ?? ''),
                'sent' => (int) ($row->sent ?? 0),
                'converted' => (int) ($row->converted ?? 0),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $ruleRows
     * @return array<string, mixed>
     */
    protected function healthPayload(array $ruleRows = []): array
    {
        unset($ruleRows);
        $last = $this->guard->lastCronRun();
        $active = 0;
        if (Schema::hasTable('svp_marketing_rules')) {
            $active = (int) SvpMarketingRule::query()->where('enabled', 1)->count();
        }
        $next = 0;
        if (is_array($last) && ! empty($last['at'])) {
            $next = (int) $last['at'] + 3600;
        } else {
            $next = now()->addHour()->startOfHour()->getTimestamp();
        }
        $cronMode = 'server';
        try {
            $mode = (string) (app(\App\Services\CronStatusService::class)->forDashboard()['scheduler_mode'] ?? 'laravel');
            $cronMode = $mode === 'laravel' ? 'server' : ($mode !== '' ? $mode : 'server');
        } catch (\Throwable) {
            $cronMode = 'server';
        }

        return [
            'last_run_at' => (int) ($last['at'] ?? 0),
            'last_processed' => (int) ($last['processed'] ?? 0),
            'last_sent' => (int) ($last['sent'] ?? 0),
            'last_skipped' => (int) ($last['skipped'] ?? 0),
            'cron_block_reason' => $this->guard->cronBlockReason(),
            'lifecycle_confirmed' => $this->guard->lifecycleConfirmed(),
            'last_cron' => $last,
            'next_scheduled_at' => $next,
            'cron_mode' => $cronMode,
            'active_rules_count' => $active,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function listRules(int $ownerId, bool $siteAdminListRules): array
    {
        if (! Schema::hasTable('svp_marketing_rules')) {
            return [];
        }
        $q = SvpMarketingRule::query()->orderBy('priority')->orderBy('id')->limit(100);
        if (! $siteAdminListRules || $ownerId > 0) {
            $q->where('owner_svp_user_id', $ownerId);
        }

        return $q->get()->map(fn ($r) => $r->toArray())->all();
    }

    protected function eligibleApprovedCount(int $ownerId): int
    {
        if (! Schema::hasTable('svp_users')) {
            return 0;
        }
        $q = SvpUser::query()->where('status', 'approved');
        if ($ownerId > 0) {
            $ids = $this->moderatableUserIds($ownerId);
            if ($ids === []) {
                return 0;
            }
            $q->whereIn('id', $ids);
        }

        return (int) $q->count();
    }

    protected function retentionRate(mixed $since, int $ownerId): float
    {
        if (! Schema::hasTable('svp_users') || ! Schema::hasTable('svp_transactions')) {
            return 0.0;
        }
        $before = $since->copy()->subDays(30);
        $scope = $ownerId > 0 ? $this->moderatableUserIds($ownerId) : null;
        if (is_array($scope) && $scope === []) {
            return 0.0;
        }

        $base = (int) DB::table('svp_users as u')
            ->join('svp_transactions as t', 't.user_id', '=', 'u.id')
            ->whereIn('t.status', ['approved', 'completed'])
            ->whereIn('t.type', ['purchase', 'renew', 'service_renew'])
            ->where('t.created_at', '<', $since)
            ->where('t.created_at', '>=', $before)
            ->when(is_array($scope), fn ($q) => $q->whereIn('u.id', $scope))
            ->selectRaw('COUNT(DISTINCT u.id) as c')
            ->value('c');
        if ($base < 1) {
            return 0.0;
        }

        $ret = (int) DB::table('svp_users as u')
            ->join('svp_transactions as t', 't.user_id', '=', 'u.id')
            ->whereIn('t.status', ['approved', 'completed'])
            ->whereIn('t.type', ['purchase', 'renew', 'service_renew'])
            ->where('t.created_at', '>=', $since)
            ->whereExists(function ($q) use ($since) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t0')
                    ->whereColumn('t0.user_id', 'u.id')
                    ->whereIn('t0.status', ['approved', 'completed'])
                    ->where('t0.created_at', '<', $since);
            })
            ->when(is_array($scope), fn ($q) => $q->whereIn('u.id', $scope))
            ->selectRaw('COUNT(DISTINCT u.id) as c')
            ->value('c');

        return round(($ret / $base) * 100, 2);
    }

    protected function newToPaidRate(mixed $since, int $ownerId): float
    {
        if (! Schema::hasTable('svp_users')) {
            return 0.0;
        }
        $scope = $ownerId > 0 ? $this->moderatableUserIds($ownerId) : null;
        if (is_array($scope) && $scope === []) {
            return 0.0;
        }

        $newUsers = SvpUser::query()
            ->where('status', 'approved')
            ->where('created_at', '>=', $since)
            ->when(is_array($scope), fn ($q) => $q->whereIn('id', $scope))
            ->count();
        if ($newUsers < 1) {
            return 0.0;
        }
        if (! Schema::hasTable('svp_transactions')) {
            return 0.0;
        }

        $paid = (int) DB::table('svp_users as u')
            ->join('svp_transactions as t', 't.user_id', '=', 'u.id')
            ->where('u.status', 'approved')
            ->where('u.created_at', '>=', $since)
            ->whereIn('t.status', ['approved', 'completed'])
            ->whereIn('t.type', ['purchase', 'renew', 'service_renew'])
            ->when(is_array($scope), fn ($q) => $q->whereIn('u.id', $scope))
            ->selectRaw('COUNT(DISTINCT u.id) as c')
            ->value('c');

        return round(($paid / $newUsers) * 100, 2);
    }

    /** @return list<int> */
    protected function moderatableUserIds(int $ownerId): array
    {
        try {
            return app(\App\Modules\Reseller\Services\ResellerScopeService::class)
                ->moderatableUserIds($ownerId);
        } catch (\Throwable) {
            return [];
        }
    }
}
