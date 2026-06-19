<?php

namespace App\Modules\Marketing\Services;

use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\Facades\DB;

class MarketingSegmentService
{
    public function __construct(protected ResellerScopeService $scope) {}

    /** @param  object|array<string, mixed>  $rule
     * @return array<int, int>
     */
    public function eligibleUserIdsForRule(object|array $rule, int $ownerSvpUserId, int $limit = 40): array
    {
        $rule = (object) (is_array($rule) ? $rule : (array) $rule);
        $seg = (string) ($rule->segment_key ?? '');
        $allowed = ['never_purchased', 'abandoned_checkout', 'expiring_renew', 'churned', 'stale_buy_funnel'];
        if (! in_array($seg, $allowed, true)) {
            return [];
        }

        $lim = max(1, min(500, $limit));
        $scopeIds = $ownerSvpUserId > 0 ? $this->scope->moderatableUserIds($ownerSvpUserId) : null;

        $ids = match ($seg) {
            'churned' => $this->churnedIds($rule, $lim),
            'never_purchased' => $this->neverPurchasedIds($rule, $lim),
            'abandoned_checkout' => $this->abandonedCheckoutIds($rule, $lim),
            'stale_buy_funnel' => $this->staleBuyFunnelIds($rule, $lim),
            'expiring_renew' => $this->expiringRenewIds($rule, $lim),
            default => [],
        };

        if ($scopeIds !== null) {
            $allowedMap = array_flip($scopeIds);
            $ids = array_values(array_filter($ids, fn ($id) => isset($allowedMap[$id])));
        }

        return $ids;
    }

    /** @return array<int, int> */
    protected function churnedIds(object $rule, int $lim): array
    {
        $after = max(1, (int) ($rule->after_days ?? 45));
        $cut = now()->subDays($after);

        return DB::table('svp_users as u')
            ->where('u.status', 'approved')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t')
                    ->whereColumn('t.user_id', 'u.id')
                    ->where('t.status', 'approved')
                    ->whereIn('t.type', ['purchase', 'renew']);
            })
            ->whereNotExists(function ($q) use ($cut) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t2')
                    ->whereColumn('t2.user_id', 'u.id')
                    ->where('t2.status', 'approved')
                    ->whereIn('t2.type', ['purchase', 'renew'])
                    ->where('t2.created_at', '>=', $cut);
            })
            ->orderBy('u.id')
            ->limit($lim)
            ->pluck('u.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<int, int> */
    protected function neverPurchasedIds(object $rule, int $lim): array
    {
        $after = max(1, (int) ($rule->after_days ?? 3));
        $cut = now()->subDays($after);

        return DB::table('svp_users as u')
            ->where('u.status', 'approved')
            ->where('u.created_at', '<=', $cut)
            ->whereNotExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t')
                    ->whereColumn('t.user_id', 'u.id')
                    ->where('t.status', 'approved')
                    ->whereIn('t.type', ['purchase', 'renew']);
            })
            ->orderBy('u.id')
            ->limit($lim)
            ->pluck('u.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<int, int> */
    protected function abandonedCheckoutIds(object $rule, int $lim): array
    {
        $hrs = max(1, (int) ($rule->pending_hours ?? 24));
        $cut = now()->subHours($hrs);

        return DB::table('svp_users as u')
            ->join('svp_transactions as t', 't.user_id', '=', 'u.id')
            ->where('u.status', 'approved')
            ->where('t.status', 'pending')
            ->where('t.type', 'purchase')
            ->where('t.created_at', '<=', $cut)
            ->distinct()
            ->orderBy('u.id')
            ->limit($lim)
            ->pluck('u.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<int, int> */
    protected function staleBuyFunnelIds(object $rule, int $lim): array
    {
        $hrs = max(1, (int) ($rule->funnel_idle_hours ?? 48));
        $cut = now()->subHours($hrs);

        return DB::table('svp_users as u')
            ->where('u.status', 'approved')
            ->whereNotNull('u.state')
            ->where('u.state', 'like', 'buy_%')
            ->whereNotExists(function ($q) use ($cut) {
                $q->select(DB::raw(1))
                    ->from('svp_transactions as t')
                    ->whereColumn('t.user_id', 'u.id')
                    ->where('t.status', 'pending')
                    ->where('t.type', 'purchase')
                    ->where('t.created_at', '>=', $cut);
            })
            ->orderBy('u.id')
            ->limit($lim)
            ->pluck('u.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    /** @return array<int, int> */
    protected function expiringRenewIds(object $rule, int $lim): array
    {
        $days = max(1, (int) ($rule->expires_within_days ?? 7));
        $end = now()->addDays($days);

        return DB::table('svp_users as u')
            ->join('svp_services as s', 's.user_id', '=', 'u.id')
            ->where('u.status', 'approved')
            ->whereNull('s.deleted_at')
            ->whereNotNull('s.expires_at')
            ->where('s.expires_at', '>', now())
            ->where('s.expires_at', '<=', $end)
            ->distinct()
            ->orderBy('u.id')
            ->limit($lim)
            ->pluck('u.id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }
}
