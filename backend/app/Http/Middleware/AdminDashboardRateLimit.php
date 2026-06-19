<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AdminDashboardRateLimit
{
    public function handle(Request $request, Closure $next, string $bucket = 'state'): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $limit = $bucket === 'mutate'
            ? (int) config('svp.admin_mutate_rate_limit_per_min', 300)
            : (int) config('svp.admin_state_rate_limit_per_min', 60);

        $key = 'dash_user:'.(int) $user->id;
        if ($limit > 0 && ! $this->allow($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        return $next($request);
    }

    protected function allow(string $bucketKey, int $limit): bool
    {
        $cacheKey = 'svp_dash_rl_'.md5($bucketKey).'_'.floor(time() / 60);
        $count = (int) Cache::increment($cacheKey);
        if ($count === 1) {
            Cache::put($cacheKey, 1, 90);
        }

        return $count <= $limit;
    }
}
