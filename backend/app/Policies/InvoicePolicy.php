<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class InvoicePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Admins can view all invoices, resellers can view their own
        return in_array($user->role, ['admin', 'reseller']);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Invoice $invoice): bool
    {
        // Admin can view all, reseller can view their own
        if ($user->role === 'admin') {
            return true;
        }
        
        return $user->role === 'reseller' && $invoice->reseller_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only system (jobs) should create invoices, or admin manually
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Invoice $invoice): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can mark invoice as paid.
     */
    public function markPaid(User $user, Invoice $invoice): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can generate/download PDF.
     */
    public function download(User $user, Invoice $invoice): bool
    {
        if ($user->role === 'admin') {
            return true;
        }
        
        return $user->role === 'reseller' && $invoice->reseller_id === $user->id;
    }
}

