<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class ResellerCreditService
{
    /**
     * Check if reseller can create user with given amount
     */
    public function canCreateUser(User $reseller, float $amount): bool
    {
        if (!$reseller->isReseller()) {
            return false;
        }

        $newDebt = $reseller->current_debt + $amount;
        return $newDebt <= ($reseller->credit_limit ?? 0);
    }

    /**
     * Deduct from reseller credit
     */
    public function deductCredit(User $reseller, float $amount): void
    {
        if (!$this->canCreateUser($reseller, $amount)) {
            throw new Exception('Credit limit exceeded');
        }

        $reseller->increment('current_debt', $amount);
    }

    /**
     * Pay reseller debt
     */
    public function payDebt(User $reseller, float $amount): void
    {
        DB::transaction(function () use ($reseller, $amount) {
            $reseller->decrement('current_debt', min($amount, $reseller->current_debt));
            
            // Create transaction record
            $reseller->transactions()->create([
                'amount' => $amount,
                'type' => 'reseller_payment',
                'gateway' => 'system',
                'status' => 'completed',
                'description' => 'پرداخت بدهی نماینده',
            ]);
        });
    }

    /**
     * Get remaining credit
     */
    public function getRemainingCredit(User $reseller): float
    {
        return max(0, ($reseller->credit_limit ?? 0) - $reseller->current_debt);
    }
}

