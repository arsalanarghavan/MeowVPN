<?php

namespace App\Services;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

class AffiliateCommissionService
{
    /**
     * Calculate and add commission for affiliate
     */
    public function addCommission(User $affiliate, float $purchaseAmount): void
    {
        if (!$affiliate->isAffiliate()) {
            return;
        }

        $commissionRate = config('app.affiliate_commission_rate', 0.1); // 10%
        $commission = $purchaseAmount * $commissionRate;

        DB::transaction(function () use ($affiliate, $commission) {
            $affiliate->increment('wallet_balance', $commission);

            Transaction::create([
                'user_id' => $affiliate->id,
                'amount' => $commission,
                'type' => 'commission',
                'gateway' => 'system',
                'status' => 'completed',
                'description' => 'کمیسیون بازاریابی',
            ]);
        });
    }

    /**
     * Get affiliate statistics
     */
    public function getAffiliateStats(User $affiliate): array
    {
        $children = $affiliate->children()->get();
        $totalInvited = $children->count();
        $totalPurchases = $children->sum(function ($child) {
            return $child->subscriptions()->count();
        });
        $totalEarnings = $affiliate->transactions()
            ->where('type', 'commission')
            ->where('status', 'completed')
            ->sum('amount');

        return [
            'total_invited' => $totalInvited,
            'total_purchases' => $totalPurchases,
            'total_earnings' => $totalEarnings,
            'current_balance' => $affiliate->wallet_balance,
        ];
    }

    /**
     * Check if affiliate can request payout
     */
    public function canRequestPayout(User $affiliate): bool
    {
        $minimumPayout = config('app.affiliate_minimum_payout', 500000);
        return $affiliate->wallet_balance >= $minimumPayout;
    }
}

