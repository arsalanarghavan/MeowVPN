<?php

namespace App\Http\Middleware;

use App\Models\DashboardUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof DashboardUser || $user->role !== 'admin') {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        return $next($request);
    }
}
