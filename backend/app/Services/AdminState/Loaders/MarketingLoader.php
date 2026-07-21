<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpMarketingOffer;
use App\Models\SvpMarketingRule;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\Marketing\MarketingGuardService;
use App\Services\Marketing\MarketingLifecycleAnalyticsService;

class MarketingLoader extends AbstractLoader
{
    public function __construct(
        protected MarketingGuardService $guard,
        protected MarketingLifecycleAnalyticsService $analytics,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsMarketing();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $p = $ctx->page('marketingOffers');
        $offers = [];
        $total = 0;
        $ownerId = $this->ownerScope($ctx);
        $windowDays = $this->analytics->normalizeWindowDays(
            (int) ($ctx->request->query('marketing_window_days', 30) ?? 30)
        );

        $payload = $this->analytics->buildDashboardPayload($windowDays, $ownerId, $ownerId < 1);
        $rules = is_array($payload['rules'] ?? null) ? $payload['rules'] : [];
        $ruleStats = is_array($payload['rule_stats'] ?? null) ? $payload['rule_stats'] : [];

        if ($this->tableExists('svp_marketing_offers')) {
            $q = SvpMarketingOffer::query()->orderByDesc('id');
            if ($ownerId > 0 && $this->tableExists('svp_marketing_rules')) {
                $q->whereIn('rule_id', function ($sub) use ($ownerId) {
                    $sub->select('id')->from('svp_marketing_rules')->where('owner_svp_user_id', $ownerId);
                });
            }
            $statusFilter = strtolower(trim((string) ($ctx->request->query('marketing_offers_status', '') ?? '')));
            if ($statusFilter !== '' && in_array($statusFilter, ['issued', 'sent', 'converted', 'expired', 'skipped'], true)) {
                $q->where('status', $statusFilter);
            }
            $total = (clone $q)->count();
            $offers = $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));
        } elseif ($rules === [] && $this->tableExists('svp_marketing_rules')) {
            $rq = SvpMarketingRule::query()->orderByDesc('id')->limit(100);
            if ($ownerId > 0) {
                $rq->where('owner_svp_user_id', $ownerId);
            }
            $rules = $this->fetchRows($rq);
        }

        $result->setTotal('marketingOffers', $total);
        $result->merge([
            'marketingOffers' => $offers,
            'marketingRules' => $rules,
            'marketingRuleStats' => $ruleStats,
            'marketingLifecycleStats' => $payload,
            'marketingLifecycleFunnel' => $payload['funnel'] ?? [],
            'marketingFunnel' => $payload['funnel'] ?? [],
            'marketingSkipBreakdown' => $payload['skip_breakdown'] ?? [],
            'marketingSegmentTrend' => $payload['segment_trend'] ?? [],
        ]);
    }

    protected function ownerScope(AdminStateContext $ctx): int
    {
        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            return $ctx->actorSvpUserId;
        }
        if ($ctx->resellerContextId > 0) {
            return $ctx->resellerContextId;
        }

        return 0;
    }
}
