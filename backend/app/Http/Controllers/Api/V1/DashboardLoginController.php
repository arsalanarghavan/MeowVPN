<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Auth\DashboardTelegramAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class DashboardLoginController extends Controller
{
    public function show(DashboardTelegramAuth $telegram): JsonResponse
    {
        return response()->json($telegram->loginPagePayload());
    }

    public function telegram(Request $request, DashboardTelegramAuth $telegram): JsonResponse
    {
        $limit = (int) config('svp.login_rate_limit_per_min', 10);
        $key = 'svp-dash-tg-login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        $auth = $request->input('telegram_auth', []);
        if (! is_array($auth) || ! $telegram->verifyWidgetPayload($auth)) {
            RateLimiter::hit($key, 60);

            return response()->json(svp_err('invalid_telegram'), 401);
        }

        $user = $telegram->resolveDashboardUserForTelegramId((int) ($auth['id'] ?? 0));
        if (! $user) {
            RateLimiter::hit($key, 60);

            return response()->json(svp_err('forbidden'), 403);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user, true);
        $request->session()->regenerate();

        return response()->json([
            'ok' => true,
            'redirect' => url('/dashboard/'),
        ]);
    }
}
