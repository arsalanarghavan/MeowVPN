<?php

namespace App\Http\Middleware;

use App\Services\SettingsStore;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class WebhookRateLimit
{
    public function __construct(protected SettingsStore $settings) {}

    public function handle(Request $request, Closure $next, string $bucket = 'ip'): Response
    {
        $key = $this->bucketKey($request, $bucket);
        $limit = $this->limitFor($request, $bucket);

        if ($limit > 0 && ! $this->allow($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        return $next($request);
    }

    protected function bucketKey(Request $request, string $bucket): string
    {
        if ($bucket === 'reseller') {
            $rid = (int) $request->route('resellerId');

            return 'reseller:'.$rid;
        }

        return 'ip:'.$this->clientIp($request);
    }

    protected function limitFor(Request $request, string $bucket): int
    {
        if ($bucket === 'reseller') {
            $env = (int) config('svp.webhook_reseller_rate_limit_per_min', 0);

            return $env > 0
                ? $env
                : (int) $this->settings->get('webhook_reseller_rate_limit_per_min', 60);
        }

        $env = (int) config('svp.webhook_rate_limit_per_min', 0);

        return $env > 0
            ? $env
            : (int) $this->settings->get('webhook_rate_limit_per_min', 120);
    }

    protected function allow(string $bucketKey, int $limit): bool
    {
        $cacheKey = 'svp_rl_'.md5($bucketKey).'_'.floor(time() / 60);
        $count = (int) Cache::increment($cacheKey);
        if ($count === 1) {
            Cache::put($cacheKey, 1, 90);
        }

        return $count <= $limit;
    }

    protected function clientIp(Request $request): string
    {
        $trust = config('svp.rate_limit_trust_forwarded_for', false);
        if (! $trust) {
            $trust = (bool) $this->settings->get('rate_limit_trust_forwarded_for', false);
        }
        if ($trust) {
            foreach (['CF-Connecting-IP', 'X-Real-IP', 'X-Forwarded-For'] as $header) {
                $raw = trim(explode(',', (string) $request->header($header))[0]);
                if ($raw !== '' && filter_var($raw, FILTER_VALIDATE_IP)) {
                    return $raw;
                }
            }
        }

        return $request->ip() ?? '0';
    }
}
