<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ZibalService;
use App\Jobs\SendTelegramNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Exception;

class TransactionController extends Controller
{
    public function __construct(
        private ZibalService $zibalService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        
        // Admin can see all transactions
        if ($user->isAdmin()) {
            $query = Transaction::query();
        } else {
            $query = Transaction::where('user_id', $user->id);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        return response()->json($query->with('user')->latest()->paginate(50));
    }

    public function show(Transaction $transaction)
    {
        $this->authorize('view', $transaction);
        return response()->json($transaction->load('user'));
    }

    public function deposit(Request $request)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:10000', // Minimum 10,000 Rials
            'gateway' => 'required|in:zibal,card_to_card',
            'proof_image' => 'nullable|image|max:2048',
        ]);

        $user = $request->user();

        try {
            return DB::transaction(function () use ($user, $data, $request) {
                // Handle proof image upload for card_to_card
                $proofImagePath = null;
                if ($request->hasFile('proof_image')) {
                    $proofImagePath = $request->file('proof_image')->store('proofs', 'public');
                }

                // Create transaction record
                $transaction = Transaction::create([
                    'user_id' => $user->id,
                    'amount' => $data['amount'],
                    'type' => 'deposit',
                    'gateway' => $data['gateway'],
                    'status' => 'pending',
                    'proof_image' => $proofImagePath,
                    'ref_id' => Str::uuid()->toString(),
                ]);

                if ($data['gateway'] === 'zibal') {
                    // Create Zibal payment request
                    $result = $this->zibalService->requestPayment(
                        (int) $data['amount'],
                        $transaction->ref_id,
                        "شارژ کیف پول - کاربر {$user->id}",
                        $user->phone ?? null
                    );

                    if (!$result || !$result['success']) {
                        $transaction->update(['status' => 'failed']);
                        throw new Exception($result['error'] ?? 'خطا در ایجاد درخواست پرداخت');
                    }

                    $transaction->update([
                        'gateway_track_id' => $result['trackId'],
                    ]);

                    return response()->json([
                        'transaction' => $transaction,
                        'payment_url' => $result['paymentUrl'],
                    ], 201);
                }

                // Card to card - needs admin approval
                // Notify admin
                $admins = User::where('role', 'admin')->get();
                foreach ($admins as $admin) {
                    if ($admin->telegram_id) {
                        SendTelegramNotification::dispatch(
                            $admin->telegram_id,
                            "💳 درخواست شارژ کارت به کارت جدید\n\n" .
                            "کاربر: {$user->username}\n" .
                            "مبلغ: " . number_format($data['amount']) . " ریال\n" .
                            "شناسه: #{$transaction->id}"
                        );
                    }
                }

                return response()->json([
                    'transaction' => $transaction,
                    'message' => 'درخواست شارژ ثبت شد. پس از بررسی توسط ادمین، موجودی شما شارژ خواهد شد.',
                ], 201);
            });
        } catch (Exception $e) {
            Log::error('Deposit failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request)
    {
        $trackId = $request->input('trackId');
        $success = $request->input('success');
        $status = $request->input('status');
        $orderId = $request->input('orderId');

        Log::info('Zibal callback received', $request->all());

        // Find transaction by ref_id (orderId) or trackId
        $transaction = Transaction::where('ref_id', $orderId)
            ->orWhere('gateway_track_id', $trackId)
            ->first();

        if (!$transaction) {
            Log::error('Transaction not found for Zibal callback', ['trackId' => $trackId, 'orderId' => $orderId]);
            return redirect(config('app.frontend_url', '/') . '/payment/failed?error=transaction_not_found');
        }

        // Check if already processed
        if ($transaction->status === 'completed') {
            return redirect(config('app.frontend_url', '/') . '/payment/success?already=true');
        }

        // If payment was not successful
        if ($success != 1) {
            $transaction->update([
                'status' => 'failed',
                'description' => $this->zibalService->getStatusMessage((int) $status),
            ]);
            
            return redirect(config('app.frontend_url', '/') . '/payment/failed?status=' . $status);
        }

        // Verify payment
        $verification = $this->zibalService->verifyPayment($trackId);

        if (!$verification || !$verification['success']) {
            $transaction->update([
                'status' => 'failed',
                'description' => $verification['error'] ?? 'خطا در تایید پرداخت',
            ]);
            
            return redirect(config('app.frontend_url', '/') . '/payment/failed?error=verification_failed');
        }

        try {
            DB::transaction(function () use ($transaction, $verification) {
                // Update transaction
                $transaction->update([
                    'status' => 'completed',
                    'gateway_ref_id' => $verification['refNumber'] ?? null,
                    'description' => 'پرداخت موفق - کارت: ' . ($verification['cardNumber'] ?? 'نامشخص'),
                ]);

                // Add to user wallet
                $transaction->user->increment('wallet_balance', $transaction->amount);

                // Send notification
                if ($transaction->user->telegram_id) {
                    SendTelegramNotification::dispatch(
                        $transaction->user->telegram_id,
                        "✅ شارژ کیف پول انجام شد\n\n" .
                        "مبلغ: " . number_format($transaction->amount) . " ریال\n" .
                        "موجودی جدید: " . number_format($transaction->user->wallet_balance) . " ریال"
                    );
                }
            });

            return redirect(config('app.frontend_url', '/') . '/payment/success');
        } catch (Exception $e) {
            Log::error('Zibal callback processing failed: ' . $e->getMessage());
            return redirect(config('app.frontend_url', '/') . '/payment/failed?error=processing_failed');
        }
    }

    public function approve(Request $request, Transaction $transaction)
    {
        $this->authorize('approve', $transaction);

        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'این تراکنش قبلاً پردازش شده است'], 400);
        }

        if ($transaction->type !== 'deposit') {
            return response()->json(['error' => 'فقط تراکنش‌های واریز قابل تایید هستند'], 400);
        }

        try {
            DB::transaction(function () use ($transaction) {
                $transaction->update(['status' => 'completed']);
                $transaction->user->increment('wallet_balance', $transaction->amount);

                // Send notification
                if ($transaction->user->telegram_id) {
                    SendTelegramNotification::dispatch(
                        $transaction->user->telegram_id,
                        "✅ شارژ کیف پول تایید شد\n\n" .
                        "مبلغ: " . number_format($transaction->amount) . " ریال\n" .
                        "موجودی جدید: " . number_format($transaction->user->wallet_balance) . " ریال"
                    );
                }
            });

            return response()->json(['message' => 'تراکنش تایید شد']);
        } catch (Exception $e) {
            Log::error('Transaction approval failed: ' . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function reject(Request $request, Transaction $transaction)
    {
        $this->authorize('reject', $transaction);

        if ($transaction->status !== 'pending') {
            return response()->json(['error' => 'این تراکنش قبلاً پردازش شده است'], 400);
        }

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $transaction->update([
            'status' => 'rejected',
            'description' => $data['reason'] ?? 'رد شده توسط ادمین',
        ]);

        // Send notification
        if ($transaction->user->telegram_id) {
            SendTelegramNotification::dispatch(
                $transaction->user->telegram_id,
                "❌ درخواست شارژ رد شد\n\n" .
                "مبلغ: " . number_format($transaction->amount) . " ریال\n" .
                "دلیل: " . ($data['reason'] ?? 'بدون توضیح')
            );
        }

        return response()->json(['message' => 'تراکنش رد شد']);
    }

    /**
     * Get pending transactions for admin
     */
    public function pending(Request $request)
    {
        $this->authorize('viewAny', Transaction::class);

        $transactions = Transaction::where('status', 'pending')
            ->where('type', 'deposit')
            ->with('user')
            ->latest()
            ->paginate(20);

        return response()->json($transactions);
    }

    /**
     * Get transaction statistics
     */
    public function stats(Request $request)
    {
        $user = $request->user();

        if (!$user->isAdmin()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_deposits' => Transaction::where('type', 'deposit')
                ->where('status', 'completed')
                ->sum('amount'),
            'today_deposits' => Transaction::where('type', 'deposit')
                ->where('status', 'completed')
                ->whereDate('created_at', today())
                ->sum('amount'),
            'pending_deposits' => Transaction::where('type', 'deposit')
                ->where('status', 'pending')
                ->count(),
            'total_purchases' => Transaction::where('type', 'purchase')
                ->where('status', 'completed')
                ->sum('amount'),
        ];

        return response()->json($stats);
    }
}
