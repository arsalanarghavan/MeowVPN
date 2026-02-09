<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use App\Models\User;

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
     * Get the rate limiting throttle key for the given request.
     */
    private function throttleKey(Request $request): string
    {
        return strtolower($request->input('email')) . '|' . $request->ip();
    }
}
