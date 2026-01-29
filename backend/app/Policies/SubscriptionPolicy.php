<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SubscriptionPolicy
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
    public function view(User $user, Subscription $subscription): bool
    {
        // Admin can view any subscription
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can view subscriptions of their users
        if ($user->isReseller()) {
            $subscriptionUser = $subscription->user;
            return $subscriptionUser && $subscriptionUser->parent_id === $user->id;
        }

        // Users can only view their own subscriptions
        return $user->id === $subscription->user_id;
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
    public function update(User $user, Subscription $subscription): bool
    {
        // Admin can update any subscription
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can update subscriptions of their users
        if ($user->isReseller()) {
            $subscriptionUser = $subscription->user;
            return $subscriptionUser && $subscriptionUser->parent_id === $user->id;
        }

        // Users can only update their own subscriptions
        return $user->id === $subscription->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Subscription $subscription): bool
    {
        // Admin can delete any subscription
        if ($user->isAdmin()) {
            return true;
        }

        // Reseller can delete subscriptions of their users
        if ($user->isReseller()) {
            $subscriptionUser = $subscription->user;
            return $subscriptionUser && $subscriptionUser->parent_id === $user->id;
        }

        // Regular users cannot delete subscriptions
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Subscription $subscription): bool
    {
        return $user->isAdmin();
    }
}

