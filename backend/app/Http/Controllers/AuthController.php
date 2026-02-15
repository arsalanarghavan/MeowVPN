<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\LoginConfirmation;
use App\Models\User;
use App\Models\AppLoginToken;
use App\Services\TelegramService;

class AuthController extends Controller
{
    /**
     * Maximum login attempts before lockout
     */
    private const MAX_LOGIN_ATTEMPTS = 5;
    
    /**
     * Lockout duration in seconds (5 minutes)
     */
    private const LOCKOUT_DURATION = 300;

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Rate limiting for login attempts
        $throttleKey = $this->throttleKey($request);
        
        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            
            throw ValidationException::withMessages([
                'email' => ["تعداد تلاش‌های ورود بیش از حد مجاز است. لطفاً {$seconds} ثانیه صبر کنید."],
            ])->status(429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_DURATION);
            
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور اشتباه است.'],
            ]);
        }

        // Clear rate limiter on successful login
        RateLimiter::clear($throttleKey);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'telegram_id' => 'nullable|integer|unique:users,telegram_id',
            'username' => 'nullable|string|unique:users,username',
            'email' => 'nullable|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'parent_id' => 'nullable|exists:users,id',
        ]);

        // Only allow parent_id when the parent is affiliate or reseller (referral flow)
        if (!empty($data['parent_id'])) {
            $parent = User::find($data['parent_id']);
            if (!$parent || !in_array($parent->role, ['affiliate', 'reseller'], true)) {
                unset($data['parent_id']);
            }
        }

        // Hash password before creating user if provided
        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        }

        // Check if user already exists (for Telegram users)
        if (!empty($data['telegram_id'])) {
            $existingUser = User::where('telegram_id', $data['telegram_id'])->first();
            if ($existingUser) {
                // Return existing user with new token
                $token = $existingUser->createToken('auth-token')->plainTextToken;
                return response()->json([
                    'user' => $existingUser,
                    'token' => $token,
                    'existing' => true,
                ]);
            }
        }

        $user = User::create($data);

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'خروج موفقیت‌آمیز بود']);
    }

    public function me(Request $request)
    {
        return response()->json($request->user()->load(['resellerProfile', 'parent']));
    }

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['رمز عبور فعلی اشتباه است.'],
            ]);
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        // Revoke all tokens except current one
        $currentTokenId = $request->user()->currentAccessToken()->id;
        $user->tokens()->where('id', '!=', $currentTokenId)->delete();

        return response()->json(['message' => 'رمز عبور با موفقیت تغییر کرد']);
    }

    /**
     * Exchange a one-time app login token (from Telegram bot) for a Sanctum API token.
     * Used by the mobile/desktop app after user taps the deep link from the bot.
     */
    public function appLogin(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        $loginToken = AppLoginToken::where('token', $request->token)->first();

        if (!$loginToken) {
            throw ValidationException::withMessages([
                'token' => ['توکن نامعتبر یا منقضی شده است.'],
            ])->status(401);
        }

        if ($loginToken->isExpired()) {
            $loginToken->delete();
            throw ValidationException::withMessages([
                'token' => ['توکن منقضی شده است. لطفاً دوباره از ربات وارد شوید.'],
            ])->status(401);
        }

        $user = $loginToken->user;
        $loginToken->delete();

        $apiToken = $user->createToken('app-auth')->plainTextToken;

        return response()->json([
            'user' => $user->load(['resellerProfile', 'parent']),
            'token' => $apiToken,
        ]);
    }

    /**
     * App login with email and password. If user has telegram_2fa_enabled and telegram_id,
     * returns pending_telegram_2fa and session_id for polling. Otherwise returns token directly.
     */
    public function appLoginEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = $this->throttleKey($request);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            throw ValidationException::withMessages([
                'email' => ["تعداد تلاش‌های ورود بیش از حد مجاز است. لطفاً {$seconds} ثانیه صبر کنید."],
            ])->status(429);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            RateLimiter::hit($throttleKey, self::LOCKOUT_DURATION);
            throw ValidationException::withMessages([
                'email' => ['ایمیل یا رمز عبور اشتباه است.'],
            ]);
        }

        RateLimiter::clear($throttleKey);

        $ipAddress = $request->ip();

        if ($user->telegram_2fa_enabled && $user->telegram_id) {
            LoginConfirmation::where('user_id', $user->id)
                ->where('status', LoginConfirmation::STATUS_PENDING)
                ->update(['status' => LoginConfirmation::STATUS_EXPIRED]);

            $confirmation = LoginConfirmation::createForUser($user, $ipAddress);

            $sent = app(TelegramService::class)->sendLoginConfirmationRequest($confirmation);

            if (!$sent) {
                $token = $user->createToken('app-auth')->plainTextToken;
                return response()->json([
                    'status' => 'ok',
                    'user' => $user->load(['resellerProfile', 'parent']),
                    'token' => $token,
                ]);
            }

            return response()->json([
                'status' => 'pending_telegram_2fa',
                'session_id' => $confirmation->session_token,
            ]);
        }

        $token = $user->createToken('app-auth')->plainTextToken;
        return response()->json([
            'status' => 'ok',
            'user' => $user->load(['resellerProfile', 'parent']),
            'token' => $token,
        ]);
    }

    /**
     * Poll for login confirmation status. App calls this when waiting for user to Allow/Reject in Telegram.
     */
    public function loginConfirm(string $sessionId)
    {
        $confirmation = LoginConfirmation::where('session_token', $sessionId)->first();

        if (!$confirmation) {
            throw ValidationException::withMessages([
                'session_id' => ['جلسه نامعتبر است.'],
            ])->status(404);
        }

        if ($confirmation->isExpired() && $confirmation->status === LoginConfirmation::STATUS_PENDING) {
            $confirmation->update(['status' => LoginConfirmation::STATUS_EXPIRED]);
            throw ValidationException::withMessages([
                'session_id' => ['زمان تایید تمام شده است. لطفاً دوباره تلاش کنید.'],
            ])->status(410);
        }

        if ($confirmation->status === LoginConfirmation::STATUS_APPROVED) {
            $token = $confirmation->api_token;
            $user = $confirmation->user;
            $confirmation->delete();
            return response()->json([
                'status' => 'approved',
                'user' => $user->load(['resellerProfile', 'parent']),
                'token' => $token,
            ]);
        }

        if ($confirmation->status === LoginConfirmation::STATUS_REJECTED) {
            $confirmation->delete();
            throw ValidationException::withMessages([
                'session_id' => ['ورود توسط شما لغو شد.'],
            ])->status(403);
        }

        if ($confirmation->status === LoginConfirmation::STATUS_EXPIRED) {
            $confirmation->delete();
            throw ValidationException::withMessages([
                'session_id' => ['زمان تایید تمام شده است. لطفاً دوباره تلاش کنید.'],
            ])->status(410);
        }

        return response()->json(['status' => 'pending']);
    }

    /**
     * Confirm or reject login 2FA. Called by the Telegram bot when user clicks Allow/End Session.
     */
    public function confirmLogin2fa(Request $request)
    {
        $request->validate([
            'session_id' => 'required|string',
            'action' => 'required|in:allow,reject',
        ]);

        $confirmation = LoginConfirmation::where('session_token', $request->session_id)->first();

        if (!$confirmation) {
            return response()->json(['error' => 'Session not found'], 404);
        }

        if ($confirmation->status !== LoginConfirmation::STATUS_PENDING) {
            return response()->json(['error' => 'Session already processed'], 400);
        }

        if ($confirmation->isExpired()) {
            $confirmation->update(['status' => LoginConfirmation::STATUS_EXPIRED]);
            return response()->json(['error' => 'Session expired'], 410);
        }

        if ($request->action === 'allow') {
            $confirmation->approve();
            return response()->json(['status' => 'approved']);
        }

        $confirmation->reject();
        return response()->json(['status' => 'rejected']);
    }

    /**
     * Create a one-time token for app login. Called by the Telegram bot only.
     * Bot sends telegram_id; user is found or created (register flow), then token is created.
     */
    public function createAppLoginToken(Request $request)
    {
        $request->validate([
            'telegram_id' => 'required|integer',
            'username' => 'nullable|string',
        ]);

        $user = User::where('telegram_id', $request->telegram_id)->first();

        if (!$user) {
            $user = User::create([
                'telegram_id' => $request->telegram_id,
                'username' => $request->username,
            ]);
        }

        $loginToken = AppLoginToken::createForUser($user);

        return response()->json([
            'token' => $loginToken->token,
            'expires_in' => AppLoginToken::EXPIRY_MINUTES * 60,
        ], 201);
    }

    /**
     * Get the rate limiting throttle key for the given request.
     */
    private function throttleKey(Request $request): string
    {
        return strtolower($request->input('email')) . '|' . $request->ip();
    }
}
