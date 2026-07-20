<?php

namespace App\Http\Middleware;

use App\Services\SettingsStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalCronSecret
{
    public function __construct(protected SettingsStore $settings) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expected = trim((string) $this->settings->get('internal_cron_secret', ''));
        if ($expected === '') {
            return response()->json(['ok' => false, 'message' => 'secret_not_configured'], 503);
        }
        $got = trim((string) $request->header('X-SVP-Internal-Secret', ''));
        if ($got === '' || ! hash_equals($expected, $got)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }
        if ($this->rateLimited($expected)) {
            return response()->json(['ok' => false, 'message' => 'rate_limited'], 429);
        }

        return $next($request);
    }

    protected function rateLimited(string $secret): bool
    {
        $key = 'svp_internal_sk_rl_'.md5($secret);
        if (Cache::get($key)) {
            return true;
        }
        Cache::put($key, 1, 60);

        return false;
    }
}
