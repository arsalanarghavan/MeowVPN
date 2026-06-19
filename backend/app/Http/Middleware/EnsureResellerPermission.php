<?php

namespace App\Http\Middleware;

use App\Models\DashboardUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if (! $user instanceof DashboardUser) {
            return response()->json(svp_err('forbidden'), 403);
        }

        if ($user->role === 'admin') {
            return $next($request);
        }

        if ($user->role !== 'reseller') {
            return response()->json(svp_err('forbidden'), 403);
        }

        $perms = is_array($user->permissions_json) ? $user->permissions_json : [];
        if (empty($perms[$permission])) {
            return response()->json(svp_err('forbidden_perm'), 403);
        }

        return $next($request);
    }
}
