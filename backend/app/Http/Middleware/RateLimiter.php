<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RateLimiter
{
    public function handle(Request $request, Closure $next, int $maxAttempts = 60, int $decayMinutes = 1): Response
    {
        $key = $this->resolveRequestSignature($request);
        $maxAttempts = (int) $maxAttempts;
        $decaySeconds = (int) ($decayMinutes * 60);

        $current = Redis::get($key);

        if ($current && $current >= $maxAttempts) {
            return response()->json([
                'error' => 'Too many requests. Please try again later.'
            ], 429);
        }

        Redis::incr($key);
        Redis::expire($key, $decaySeconds);

        return $next($request);
    }

    protected function resolveRequestSignature(Request $request): string
    {
        $identifier = $request->user()?->id ?? $request->ip();
        $route = $request->route()?->getName() ?? $request->path();
        
        return "rate_limit:{$route}:{$identifier}";
    }
}

