<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TransactionPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Transaction $transaction): bool
    {
        // Admin can view any transaction
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can view transactions of their users
        if ($user->isReseller()) {
            $transactionUser = $transaction->user;
            return $transactionUser && $transactionUser->parent_id === $user->id;
        }

        // Users can only view their own transactions
        return $user->id === $transaction->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Transaction $transaction): bool
    {
        // Only admin can update transactions
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can approve the transaction.
     */
    public function approve(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can reject the transaction.
     */
    public function reject(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Transaction $transaction): bool
    {
        // Only admin can delete transactions
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return $user->isAdmin();
    }
}

