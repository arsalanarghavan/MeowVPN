<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view the model.
     * Admin: all; Reseller: self + children; User: self only.
     */
    public function view(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isReseller()) {
            return $actor->id === $target->id || $target->parent_id === $actor->id;
        }

        return $actor->id === $target->id;
    }

    /**
     * Determine whether the user can update the model.
     * Admin: all; Reseller: self + children; User: self only.
     */
    public function update(User $actor, User $target): bool
    {
        if ($actor->isAdmin()) {
            return true;
        }

        if ($actor->isReseller()) {
            return $actor->id === $target->id || $target->parent_id === $actor->id;
        }

        return $actor->id === $target->id;
    }

    /**
     * Determine whether the user can delete the model.
     * Only admin may delete users.
     */
    public function delete(User $actor, User $target): bool
    {
        return $actor->isAdmin();
    }

    /**
     * Fields that only admin may change when updating a user.
     */
    public static function adminOnlyUpdateFields(): array
    {
        return ['role', 'credit_limit'];
    }
}
