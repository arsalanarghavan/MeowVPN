<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AffiliateCommissionService;
use App\Models\PayoutRequest;
use App\Models\User;
use App\Jobs\SendTelegramNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class AffiliateController extends Controller
{
    public function __construct(
        private AffiliateCommissionService $commissionService
    ) {}

    public function stats(Request $request)
    {
        $stats = $this->commissionService->getAffiliateStats($request->user());
        return response()->json($stats);
    }

    public function getLink(Request $request)
    {
        $botUsername = config('services.telegram.bot_username', 'yourbot');
        $userId = $request->user()->id;
        $link = "https://t.me/{$botUsername}?start={$userId}";
        
        return response()->json(['link' => $link]);
    }

    public function requestPayout(Request $request)
    {
        $user = $request->user();

        if (!$this->commissionService->canRequestPayout($user)) {
            $minimumPayout = config('app.affiliate_minimum_payout', 500000);
            return response()->json([
                'error' => 'حداقل مبلغ برای درخواست تسویه رعایت نشده است',
                'minimum' => $minimumPayout,
                'balance' => $user->wallet_balance,
            ], 400);
        }

        // Check for existing pending request
        $existingRequest = PayoutRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existingRequest) {
            return response()->json([
                'error' => 'شما یک درخواست تسویه در انتظار دارید',
                'existing_request' => $existingRequest,
            ], 400);
        }

        $data = $request->validate([
            'card_number' => 'required|string|min:16|max:19',
            'card_holder' => 'nullable|string|max:100',
        ]);

        try {
            $payoutRequest = PayoutRequest::create([
                'user_id' => $user->id,
                'amount' => $user->wallet_balance,
                'card_number' => preg_replace('/[^0-9]/', '', $data['card_number']),
                'card_holder' => $data['card_holder'] ?? null,
                'status' => 'pending',
            ]);

            // Notify admins
            $admins = User::where('role', 'admin')->get();
            foreach ($admins as $admin) {
                if ($admin->telegram_id) {
                    SendTelegramNotification::dispatch(
                        $admin->telegram_id,
                        "💰 درخواست تسویه حساب جدید\n\n" .
                        "کاربر: {$user->username}\n" .
                        "مبلغ: " . number_format($payoutRequest->amount) . " ریال\n" .
                        "شماره کارت: {$payoutRequest->card_number}\n" .
                        "شناسه: #{$payoutRequest->id}"
                    );
                }
            }

            return response()->json([
                'message' => 'درخواست تسویه حساب با موفقیت ثبت شد',
                'payout_request' => $payoutRequest,
            ], 201);
        } catch (Exception $e) {
            Log::error('Payout request failed: ' . $e->getMessage());
            return response()->json(['error' => 'خطا در ثبت درخواست'], 500);
        }
    }

    /**
     * List all payout requests (admin only)
     */
    public function payouts(Request $request)
    {
        $query = PayoutRequest::with('user');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->latest()->paginate(20));
    }

    /**
     * Get pending payout requests (admin only)
     */
    public function pendingPayouts()
    {
        $payouts = PayoutRequest::with('user')
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return response()->json($payouts);
    }

    /**
     * Approve payout request (admin only)
     */
    public function approvePayout(Request $request, PayoutRequest $payoutRequest)
    {
        if ($payoutRequest->status !== 'pending') {
            return response()->json([
                'error' => 'این درخواست قبلاً پردازش شده است',
            ], 400);
        }

        try {
            return DB::transaction(function () use ($payoutRequest) {
                $user = $payoutRequest->user;

                // Check if user still has enough balance
                if ($user->wallet_balance < $payoutRequest->amount) {
                    return response()->json([
                        'error' => 'موجودی کاربر کافی نیست',
                        'balance' => $user->wallet_balance,
                        'requested' => $payoutRequest->amount,
                    ], 400);
                }

                // Deduct from user wallet
                $user->decrement('wallet_balance', $payoutRequest->amount);

                // Update payout request
                $payoutRequest->update([
                    'status' => 'approved',
                    'paid_at' => now(),
                ]);

                // Notify user
                if ($user->telegram_id) {
                    SendTelegramNotification::dispatch(
                        $user->telegram_id,
                        "✅ درخواست تسویه حساب تایید شد\n\n" .
                        "مبلغ: " . number_format($payoutRequest->amount) . " ریال\n" .
                        "شماره کارت: {$payoutRequest->card_number}\n\n" .
                        "مبلغ به زودی به حساب شما واریز خواهد شد."
                    );
                }

                return response()->json([
                    'message' => 'درخواست تسویه تایید شد',
                    'payout_request' => $payoutRequest->fresh(),
                ]);
            });
        } catch (Exception $e) {
            Log::error('Payout approval failed: ' . $e->getMessage());
            return response()->json(['error' => 'خطا در تایید درخواست'], 500);
        }
    }

    /**
     * Reject payout request (admin only)
     */
    public function rejectPayout(Request $request, PayoutRequest $payoutRequest)
    {
        if ($payoutRequest->status !== 'pending') {
            return response()->json([
                'error' => 'این درخواست قبلاً پردازش شده است',
            ], 400);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $payoutRequest->update([
            'status' => 'rejected',
            'admin_note' => $data['reason'] ?? 'رد شده توسط ادمین',
        ]);

        // Notify user
        $user = $payoutRequest->user;
        if ($user->telegram_id) {
            SendTelegramNotification::dispatch(
                $user->telegram_id,
                "❌ درخواست تسویه حساب رد شد\n\n" .
                "مبلغ: " . number_format($payoutRequest->amount) . " ریال\n" .
                "دلیل: " . ($data['reason'] ?? 'بدون توضیح')
            );
        }

        return response()->json([
            'message' => 'درخواست تسویه رد شد',
            'payout_request' => $payoutRequest,
        ]);
    }

    /**
     * Get user's own payout requests
     */
    public function myPayouts(Request $request)
    {
        $payouts = PayoutRequest::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($payouts);
    }
}
