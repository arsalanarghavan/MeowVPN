<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\DashboardUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $username = (string) ($request->input('log') ?? $request->input('username') ?? '');
        $password = (string) ($request->input('pwd') ?? $request->input('password') ?? '');
        $remember = (bool) ($request->input('remember') ?? false);

        if (trim($username) === '' || $password === '') {
            return response()->json(svp_err('invalid_credentials'), 401);
        }

        $limit = (int) config('svp.login_rate_limit_per_min', 10);
        $key = 'svp-dash-login-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        RateLimiter::hit($key, 60);

        $user = DashboardUser::query()->where('username', $username)->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(svp_err('invalid_credentials'), 401);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $redirect = (string) ($request->input('redirect_to') ?? '');
        if ($redirect === ''
            || ! str_starts_with($redirect, '/')
            || str_starts_with($redirect, '//')
            || str_contains($redirect, '://')) {
            $redirect = url('/dashboard/');
        }

        return response()->json([
            'ok' => true,
            'redirect' => $redirect,
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['ok' => true]);
    }

    /** Issue Sanctum personal access token (§8 optional Bearer API). */
    public function token(Request $request): JsonResponse
    {
        $username = (string) ($request->input('username') ?? $request->input('log') ?? '');
        $password = (string) ($request->input('password') ?? $request->input('pwd') ?? '');

        if (trim($username) === '' || $password === '') {
            return response()->json(svp_err('invalid_credentials'), 401);
        }

        $limit = (int) config('svp.login_rate_limit_per_min', 10);
        $key = 'svp-dash-token-ip:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(svp_err('rate_limited'), 429);
        }

        RateLimiter::hit($key, 60);

        $user = DashboardUser::query()->where('username', $username)->first();
        if (! $user || ! Hash::check($password, $user->password)) {
            return response()->json(svp_err('invalid_credentials'), 401);
        }

        RateLimiter::clear($key);
        $plain = $user->createToken('dashboard-api', ['dashboard-api'])->plainTextToken;

        return response()->json(svp_ok([
            'token' => $plain,
            'token_type' => 'Bearer',
        ]));
    }
}
