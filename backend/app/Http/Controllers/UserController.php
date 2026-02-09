<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('role')) {
            $query->where('role', $request->role);
        }

        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Support has_telegram filter for bot compatibility
        if ($request->has('has_telegram')) {
            if ($request->boolean('has_telegram')) {
                $query->whereNotNull('telegram_id');
            } else {
                $query->whereNull('telegram_id');
            }
        }

        $perPage = min((int) $request->get('per_page', 50), 100);
        return response()->json($query->paginate($perPage));
    }

    public function show(User $user)
    {
        $this->authorize('view', $user);
        return response()->json($user->load(['parent', 'resellerProfile', 'subscriptions']));
    }

    public function update(Request $request, User $user)
    {
        $this->authorize('update', $user);

        $data = $request->validate([
            'username' => 'sometimes|string|unique:users,username,' . $user->id,
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'role' => 'sometimes|in:admin,reseller,affiliate,user',
            'credit_limit' => 'sometimes|numeric|min:0',
        ]);

        // Only admin may change role and credit_limit
        if (!$request->user()->isAdmin()) {
            $data = collect($data)->except(\App\Policies\UserPolicy::adminOnlyUpdateFields())->all();
        }

        $user->update($data);
        return response()->json($user);
    }

    public function destroy(User $user)
    {
        $this->authorize('delete', $user);
        $user->delete();
        return response()->json(['message' => 'User deleted']);
    }
}

