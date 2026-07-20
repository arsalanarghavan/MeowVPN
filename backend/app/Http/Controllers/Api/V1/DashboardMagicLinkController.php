<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\DashboardMagicLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class DashboardMagicLinkController extends Controller
{
    public function issue(Request $request, DashboardMagicLinkService $magic): JsonResponse
    {
        $limit = (int) config('svp.login_rate_limit_per_min', 10);
        $key = 'svp-dash-magic-issue-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        $platform = (string) $request->input('platform', 'telegram');
        $platformUserId = (int) $request->input('platform_user_id', 0);
        $locale = $request->input('locale');
        if ($platformUserId < 1) {
            RateLimiter::hit($key, 60);

            return response()->json(svp_err('invalid'), 400);
        }

        $url = $magic->buildUrl(
            $platform,
            $platformUserId,
            is_string($locale) && $locale !== '' ? $locale : null
        );
        RateLimiter::clear($key);

        return response()->json(svp_ok([
            'url' => $url,
            'ttl' => DashboardMagicLinkService::TTL,
        ]));
    }

    public function consume(Request $request, DashboardMagicLinkService $magic): JsonResponse
    {
        $limit = (int) config('svp.login_rate_limit_per_min', 10);
        $key = 'svp-dash-magic-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        $params = array_merge($request->query(), $request->all());
        $result = $magic->consume($params, (bool) $request->input('remember', true));
        if (! ($result['ok'] ?? false)) {
            RateLimiter::hit($key, 60);

            return response()->json([
                'ok' => false,
                'code' => (string) ($result['code'] ?? 'invalid_link'),
            ], 401);
        }

        RateLimiter::clear($key);

        return response()->json([
            'ok' => true,
            'redirect' => (string) ($result['redirect'] ?? url('/dashboard/')),
        ]);
    }
}
