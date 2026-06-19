<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Services\Commerce\TransactionFulfillService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class BotCommerceCheckoutService
{
    public function __construct(
        protected SettingsStore $settings,
        protected TransactionFulfillService $fulfill,
        protected BotPlanPricingService $pricing,
        protected BotDiscountService $discounts,
        protected BotRuntime $runtime,
    ) {}

    /** @return array<string, mixed> */
    public function createPurchaseTransaction(SvpUser $user, BotContext $ctx, int $planId, float $amount, int $extraGb = 0, int $clientsCount = 1, string $discountCode = ''): array
    {
        $plan = SvpPlan::query()->find($planId);
        if (! $plan) {
            return ['ok' => false, 'message' => 'plan_not_found'];
        }

        $expected = $this->pricing->calculatePurchaseAmount($plan, $extraGb, $clientsCount);
        if ($amount > 0 && abs($amount - $expected) > 0.01) {
            return ['ok' => false, 'message' => 'amount_mismatch'];
        }
        $amount = $expected;

        $meta = [
            'plan_id' => $planId,
            'volume_gb' => $extraGb > 0 ? $extraGb : null,
            'extra_gb' => $extraGb,
            'clients_count' => $clientsCount,
            'base_amount' => $amount,
            'discount_code' => $discountCode,
            'platform' => $ctx->platform,
        ];

        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode($meta),
            'created_at' => now(),
        ]);

        if ($discountCode !== '') {
            $this->applyDiscountCode($txId, $discountCode, $user->id);
        }

        $tx = DB::table('svp_transactions')->where('id', $txId)->first();

        return ['ok' => true, 'transaction_id' => $txId, 'amount' => (float) ($tx->amount ?? $amount)];
    }

    /** @return array<string, mixed> */
    public function createTopupTransaction(SvpUser $user, BotContext $ctx, float $amount): array
    {
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'invalid_amount'];
        }

        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => $amount,
            'type' => 'wallet_topup',
            'status' => 'pending',
            'meta_json' => json_encode(['platform' => $ctx->platform, 'base_amount' => $amount]),
            'created_at' => now(),
        ]);

        return ['ok' => true, 'transaction_id' => $txId, 'amount' => $amount];
    }

    /** @return array<string, mixed> */
    public function applyDiscountCode(int $txId, string $code, int $userId): array
    {
        $result = $this->discounts->validateAndApply($txId, $code, $userId);
        if (empty($result['ok'])) {
            return $result;
        }

        return [
            'ok' => true,
            'amount' => (float) ($result['amount'] ?? 0),
            'discount_code' => strtoupper(trim($code)),
        ];
    }

    /** @return array<string, mixed> */
    public function clearDiscount(int $txId): array
    {
        $result = $this->discounts->clearFromTransaction($txId);

        return $result;
    }

    /** @return array<string, mixed> */
    public function payWithWallet(SvpUser $user, int $txId, bool $partial = false): array
    {
        return DB::transaction(function () use ($user, $txId, $partial) {
            $tx = DB::table('svp_transactions')
                ->where('id', $txId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();
            if (! $tx || (string) $tx->status !== 'pending') {
                return ['ok' => false, 'message' => 'bad_tx'];
            }

            $amount = (float) $tx->amount;
            $lockedUser = SvpUser::query()->lockForUpdate()->find($user->id);
            if (! $lockedUser) {
                return ['ok' => false, 'message' => 'bad_user'];
            }
            $balance = (float) $lockedUser->balance;

            if ($partial) {
                if ($balance <= 0) {
                    return ['ok' => false, 'message' => 'insufficient_balance'];
                }
                $walletUsed = min($balance, $amount);
                $remaining = max(0, $amount - $walletUsed);
                $lockedUser->balance = bcsub((string) $balance, (string) $walletUsed, 2);
                $lockedUser->save();

                $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
                $meta = is_array($meta) ? $meta : [];
                $meta['wallet_partial'] = $walletUsed;
                $meta['remaining_amount'] = $remaining;

                DB::table('svp_transactions')->where('id', $txId)->update([
                    'amount' => $remaining,
                    'meta_json' => json_encode($meta),
                ]);

                return ['ok' => true, 'partial' => true, 'wallet_used' => $walletUsed, 'remaining' => $remaining];
            }

            if ($balance < $amount) {
                return ['ok' => false, 'message' => 'insufficient_balance'];
            }

            if ((string) $tx->type === 'wallet_topup') {
                return ['ok' => false, 'message' => 'bad_tx'];
            }

            if (! $this->fulfill->debitWallet((int) $lockedUser->id, $amount)) {
                return ['ok' => false, 'message' => 'insufficient_balance'];
            }

            $result = $this->fulfill->fulfillByTransaction($txId, 'wallet');
            if (empty($result['ok'])) {
                $this->fulfill->refundWallet((int) $lockedUser->id, $amount);

                return ['ok' => false, 'message' => (string) ($result['reason'] ?? 'fulfill_failed')];
            }

            return $result;
        });
    }

    /** @return array<string, mixed> */
    public function fulfillBalePayment(SvpUser $user, int $txId): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->where('user_id', $user->id)->first();
        if (! $tx || (string) $tx->status !== 'pending') {
            return ['ok' => false, 'message' => 'bad_tx'];
        }

        if ((string) $tx->type === 'wallet_topup') {
            return DB::transaction(function () use ($user, $txId, $tx) {
                if (! $this->fulfill->claimPendingTransaction($txId)) {
                    return ['ok' => false, 'message' => 'bad_tx'];
                }
                $this->fulfill->creditWallet((int) $user->id, (float) $tx->amount);
                DB::table('svp_transactions')->where('id', $txId)->update(['status' => 'approved']);

                return ['ok' => true, 'topup' => true];
            });
        }

        return $this->fulfill->fulfillByTransaction($txId, 'bale_wallet');
    }

    /** @return array<string, mixed> */
    public function createC2cReceipt(SvpUser $user, int $txId, ?int $cardId = null): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->where('user_id', $user->id)->first();
        if (! $tx) {
            return ['ok' => false, 'message' => 'bad_tx'];
        }

        $existing = DB::table('svp_receipts')
            ->where('transaction_id', $txId)
            ->where('status', 'pending')
            ->first();
        if ($existing) {
            return [
                'ok' => true,
                'receipt_id' => (int) $existing->id,
                'transaction_id' => $txId,
                'duplicate' => true,
            ];
        }

        $cardQ = DB::table('svp_cards')->where('active', true)->orderBy('priority');
        if ($cardId > 0) {
            $cardQ->where('id', $cardId);
        }
        $card = $cardQ->first();

        $receiptId = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => (float) $tx->amount,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        return [
            'ok' => true,
            'receipt_id' => $receiptId,
            'transaction_id' => $txId,
            'card' => $card,
        ];
    }

    /** @return array<string, mixed> */
    public function sendBaleInvoice(BotContext $ctx, SvpUser $user, int $chatId, int $txId, string $title = 'Payment'): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->where('user_id', $user->id)->first();
        if (! $tx || $ctx->platform !== 'bale') {
            return ['ok' => false, 'message' => 'bad_tx'];
        }

        $amount = (int) round((float) $tx->amount);
        $sent = $this->runtime->sendInvoice($ctx, [
            'chat_id' => $chatId,
            'title' => $title,
            'description' => 'Transaction #'.$txId,
            'payload' => 'tx:'.$txId,
            'provider_token' => '',
            'currency' => 'IRR',
            'prices' => json_encode([['label' => $title, 'amount' => $amount]]),
        ]);

        return ['ok' => $sent !== null, 'invoice' => $sent];
    }

    /** @return array<string, mixed> */
    public function createCryptoPaymentLink(SvpUser $user, int $txId): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->where('user_id', $user->id)->first();
        if (! $tx) {
            return ['ok' => false, 'message' => 'bad_tx'];
        }

        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');
        $link = $base.'/portal?pay='.$txId;

        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['crypto_checkout'] = true;
        DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta)]);

        return ['ok' => true, 'link' => $link];
    }

    public function validateBalePreCheckout(int $txId, int $expectedAmount): bool
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        if (! $tx || (string) $tx->status !== 'pending') {
            return false;
        }

        return (int) round((float) $tx->amount) === $expectedAmount;
    }

    /** @return array<int, string> */
    public function enabledPaymentMethods(BotContext $ctx): array
    {
        $methods = $this->settings->get('payment_methods', ['c2c', 'site_wallet']);
        if ($ctx->resellerSvpUserId > 0) {
            $resellerMethods = $this->settings->get('reseller_payment_methods.'.$ctx->resellerSvpUserId, null);
            if (is_array($resellerMethods) && $resellerMethods !== []) {
                $methods = $resellerMethods;
            }
        }
        if (! is_array($methods)) {
            return ['c2c', 'site_wallet'];
        }
        $out = array_values(array_filter(array_map('strval', $methods)));
        if (svp_modules()->isEnabled('crypto') && ! in_array('crypto', $out, true)) {
            $out[] = 'crypto';
        }

        return $out;
    }

    /** @return array<string, mixed> */
    public function transactionMeta(int $txId): array
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        if (! $tx) {
            return [];
        }
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);

        return is_array($meta) ? $meta : [];
    }

    public function transactionAmount(int $txId): float
    {
        return (float) (DB::table('svp_transactions')->where('id', $txId)->value('amount') ?? 0);
    }
}
