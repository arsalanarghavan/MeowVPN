<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpPlan;
use App\Services\Commerce\AdminUserOpsService;

class BotPlanPricingService
{
    public function __construct(protected AdminUserOpsService $adminOps) {}

    public function calculatePurchaseAmount(SvpPlan $plan, int $extraGb = 0, int $clientsCount = 1): float
    {
        $pricingType = (string) ($plan->pricing_type ?? 'fixed');
        if ($pricingType === 'per_gb') {
            $gb = $this->normalizeGb($plan, $extraGb);
            $amount = $this->adminOps->priceNewService($plan, $gb);
        } else {
            $amount = $this->adminOps->priceNewService($plan, null);
            if ($extraGb > 0 && (float) ($plan->price_per_gb ?? 0) > 0) {
                $amount += round((float) $plan->price_per_gb * $extraGb, 2);
            }
        }

        $slots = max(1, (int) ($plan->clients_count ?? 1));
        if ($clientsCount > $slots) {
            $amount += round($amount * (($clientsCount / $slots) - 1), 2);
        }

        return max(0, $amount);
    }

    public function planNeedsTrafficPrompt(SvpPlan $plan): bool
    {
        if ((string) ($plan->pricing_type ?? '') === 'per_gb') {
            return true;
        }

        return (int) ($plan->traffic_gb ?? 0) > 0
            || (float) ($plan->price_per_gb ?? 0) > 0
            || (int) ($plan->traffic_gb_max ?? 0) > (int) ($plan->traffic_gb ?? 0);
    }

    public function planNeedsClientsPrompt(SvpPlan $plan): bool
    {
        return (int) ($plan->clients_count ?? 1) > 1;
    }

    public function normalizeGb(SvpPlan $plan, int $gb): int
    {
        $min = max(1, (int) ($plan->traffic_gb_min ?? 1));
        $max = max($min, (int) ($plan->traffic_gb_max ?? max($min, (int) ($plan->traffic_gb ?? $min))));
        if ($gb < 1) {
            $gb = (int) ($plan->traffic_gb ?? $min);
        }

        return max($min, min($max, $gb));
    }
}
