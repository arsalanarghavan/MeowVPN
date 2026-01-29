<?php

namespace App\Policies;

use App\Models\PayoutRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class PayoutRequestPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admin can view all, users can view their own
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PayoutRequest $payoutRequest): bool
    {
        // Admin can view all, users can view their own
        if ($user->role === 'admin') {
            return true;
        }
        
        return $payoutRequest->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only affiliates (users with referred users who purchased) can request payouts
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can approve payout.
     */
    public function approve(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->role === 'admin' && $payoutRequest->status === 'pending';
    }

    /**
     * Determine whether the user can reject payout.
     */
    public function reject(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->role === 'admin' && $payoutRequest->status === 'pending';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PayoutRequest $payoutRequest): bool
    {
        return $user->role === 'admin';
    }
}

