<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpMarketingOffer;
use App\Models\SvpMarketingRule;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\Marketing\MarketingGuardService;

class MarketingLoader extends AbstractLoader
{
    public function __construct(protected MarketingGuardService $guard) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsMarketing();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $p = $ctx->page('marketingOffers');
        $offers = [];
        $rules = [];
        $total = 0;

        if ($this->tableExists('svp_marketing_offers')) {
            $q = SvpMarketingOffer::query()->orderByDesc('id');
            $total = (clone $q)->count();
            $offers = $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));
        }

        if ($this->tableExists('svp_marketing_rules')) {
            $rules = $this->fetchRows(SvpMarketingRule::query()->orderByDesc('id')->limit(100));
        }

        $ruleStats = [];
        if ($this->tableExists('svp_marketing_offers') && $this->tableExists('svp_marketing_rules')) {
            $since = now()->subDays(30);
            foreach ($rules as $rule) {
                $rid = (int) ($rule['id'] ?? 0);
                if ($rid < 1) {
                    continue;
                }
                $sent = SvpMarketingOffer::query()
                    ->where('rule_id', $rid)
                    ->whereIn('status', ['sent', 'converted'])
                    ->where('sent_at', '>=', $since)
                    ->count();
                $converted = SvpMarketingOffer::query()
                    ->where('rule_id', $rid)
                    ->where('status', 'converted')
                    ->where('sent_at', '>=', $since)
                    ->count();
                $ruleStats[] = [
                    'ruleId' => $rid,
                    'sent' => $sent,
                    'converted' => $converted,
                ];
            }
        }

        $windowDays = max(7, min(90, (int) ($ctx->request->query('marketing_window_days', 30) ?? 30)));
        $lifecycle = $this->guard->lifecycleStatsStub($windowDays);

        $result->setTotal('marketingOffers', $total);
        $result->merge([
            'marketingOffers' => $offers,
            'marketingRules' => $rules,
            'marketingRuleStats' => $ruleStats,
            'marketingLifecycleStats' => $lifecycle,
            'marketingLifecycleFunnel' => [],
        ]);
    }
}
