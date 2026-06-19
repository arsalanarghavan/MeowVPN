<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HealthMetricsAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('sanctum')->check() || Auth::guard('web')->check()) {
            return $next($request);
        }

        $token = (string) config('svp.health_deep_token', '');
        if ($token !== '' && hash_equals($token, (string) $request->header('X-Health-Token', ''))) {
            return $next($request);
        }

        return response()->json(svp_err('forbidden'), 403);
    }
}
