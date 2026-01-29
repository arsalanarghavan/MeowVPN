<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TicketPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // All authenticated users can view their tickets, admin can view all
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Ticket $ticket): bool
    {
        // Admin can view all, users can view their own
        if ($user->role === 'admin') {
            return true;
        }
        
        return $ticket->user_id === $user->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // All authenticated users can create tickets
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can reply to the ticket.
     */
    public function reply(User $user, Ticket $ticket): bool
    {
        // Admin can reply to all, users can reply to their own (if not closed)
        if ($user->role === 'admin') {
            return true;
        }
        
        return $ticket->user_id === $user->id && $ticket->status !== 'closed';
    }

    /**
     * Determine whether the user can close the ticket.
     */
    public function close(User $user, Ticket $ticket): bool
    {
        // Admin can close all, users can close their own
        if ($user->role === 'admin') {
            return true;
        }
        
        return $ticket->user_id === $user->id;
    }

    /**
     * Determine whether the user can reopen the ticket.
     */
    public function reopen(User $user, Ticket $ticket): bool
    {
        // Only admin can reopen tickets
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can assign the ticket.
     */
    public function assign(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can change priority.
     */
    public function updatePriority(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->role === 'admin';
    }
}

