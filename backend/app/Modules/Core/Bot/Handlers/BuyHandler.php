<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Jobs\DeferredC2cInvoiceJob;
use App\Modules\Core\Bot\Jobs\DeferredPurchaseCheckoutJob;
use App\Modules\Core\Bot\Services\BotCommerceCheckoutService;
use App\Modules\Core\Bot\Services\BotDeferredCacheService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotPlanPricingService;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class BuyHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
        protected KeyboardBuilder $keyboards,
        protected BotCommerceCheckoutService $checkout,
        protected BotPlanPricingService $planPricing,
        protected BotDeferredCacheService $deferredCache,
    ) {}

    public function showCategoryPicker(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $cats = DB::table('svp_plan_categories')->where('active', true)->orderBy('sort_order')->limit(12)->get();
        if ($cats->isEmpty()) {
            $this->showPlanPicker($ctx, $user, $chatId);

            return;
        }
        $rows = [];
        foreach ($cats as $cat) {
            $slug = (string) ($cat->slug ?? $cat->id);
            $rows[] = [[
                'text' => (string) ($cat->label ?? $slug),
                'callback_data' => 'buy:g:'.$slug,
            ]];
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.pick_category', $user, 'Choose category:'), [
            'reply_markup' => $this->keyboards->inline($rows),
        ]);
    }

    public function showPlanPicker(BotContext $ctx, SvpUser $user, int $chatId, ?string $category = null): void
    {
        $q = SvpPlan::query()->where('active', true)->orderBy('sort_order');
        if ($category) {
            $q->where('category', $category);
        }
        if ($ctx->resellerSvpUserId > 0) {
            $q->where(function ($sub) use ($ctx) {
                $sub->where('owner_svp_user_id', $ctx->resellerSvpUserId)
                    ->orWhere('owner_svp_user_id', 0)
                    ->orWhereNull('owner_svp_user_id');
            });
        }
        $plans = $q->limit(20)->get();
        $rows = [];
        foreach ($plans as $plan) {
            $preview = $this->planPricing->calculatePurchaseAmount($plan, 0, 1);
            $rows[] = [[
                'text' => (string) $plan->name.' — '.number_format($preview),
                'callback_data' => 'buy:cf:'.$plan->id,
            ]];
        }
        if ($rows === []) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.no_plans', $user));

            return;
        }
        if ($category) {
            $rows[] = [['text' => '←', 'callback_data' => 'buy:g:all']];
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.pick_plan', $user, 'Choose a plan:'), [
            'reply_markup' => $this->keyboards->inline($rows),
        ]);
    }

    public function sendDeferredCheckout(BotContext $ctx, SvpUser $user, int $chatId, int $planId, int $extraGb = 0, int $clientsCount = 1): void
    {
        $plan = SvpPlan::query()->find($planId);
        if (! $plan) {
            return;
        }
        $amount = $this->planPricing->calculatePurchaseAmount($plan, $extraGb, $clientsCount);
        $created = $this->checkout->createPurchaseTransaction($user, $ctx, $planId, $amount, $extraGb, $clientsCount);
        if (empty($created['ok'])) {
            $this->runtime->sendMessage($ctx, $chatId, '⛔ checkout failed');

            return;
        }
        $txId = (int) ($created['transaction_id'] ?? 0);
        $this->state->set($user, 'buy_checkout', [
            'transaction_id' => $txId,
            'plan_id' => $planId,
            'extra_gb' => $extraGb,
            'amount' => $amount,
        ]);
        $this->sendCheckoutSummary($ctx, $user, $chatId, $plan, $amount, $extraGb, $txId);
    }

    /** @param  array<string, mixed>  $payload */
    public function handleCallback(BotContext $ctx, SvpUser $user, array $payload): void
    {
        $parts = (array) ($payload['parts'] ?? []);
        $chatId = (int) ($payload['chat_id'] ?? 0);
        if (($parts[0] ?? '') !== 'buy' || ! isset($parts[1])) {
            return;
        }

        $action = (string) $parts[1];
        if ($action === 'p' && isset($parts[2])) {
            $this->dispatchDeferredCheckout($user->id, $chatId, $ctx->platform, (int) $parts[2], 0, 1);

            return;
        }
        if ($action === 'cf' && isset($parts[2])) {
            $planId = (int) $parts[2];
            $extraGb = isset($parts[3]) ? (int) $parts[3] : 0;
            $clients = isset($parts[4]) ? (int) $parts[4] : 1;
            $plan = SvpPlan::query()->find($planId);
            if ($plan && $this->planPricing->planNeedsTrafficPrompt($plan) && ! isset($parts[3])) {
                $this->state->set($user, 'buy_choose_traffic', ['plan_id' => $planId]);
                $min = max(1, (int) ($plan->traffic_gb_min ?? 1));
                $max = max($min, (int) ($plan->traffic_gb_max ?? $min));
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
                    $this->texts->getForUser('msg.buy.prompt_traffic', $user, 'Send GB ({min}-{max})'),
                    ['min' => $min, 'max' => $max]
                ));

                return;
            }
            if ($plan && $this->planPricing->planNeedsClientsPrompt($plan) && ! isset($parts[4])) {
                $this->state->set($user, 'buy_choose_clients', ['plan_id' => $planId, 'extra_gb' => $extraGb]);
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.prompt_clients', $user, 'Send client count'));

                return;
            }
            $this->dispatchDeferredCheckout($user->id, $chatId, $ctx->platform, $planId, $extraGb, $clients);

            return;
        }
        if ($action === 'g' && isset($parts[2])) {
            $cat = (string) $parts[2];
            if ($cat === 'all') {
                $this->showPlanPicker($ctx, $user, $chatId);
            } else {
                $this->showPlanPicker($ctx, $user, $chatId, $cat);
            }

            return;
        }
        if ($action === 'c' && isset($parts[2], $parts[3])) {
            $this->showPlanPicker($ctx, $user, $chatId, (string) $parts[3]);

            return;
        }
        if ($action === 'x') {
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return;
        }
        if ($action === 'dc' && isset($parts[2])) {
            $txId = (int) $parts[2];
            $this->state->set($user, 'buy_discount', ['transaction_id' => $txId]);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.prompt_discount', $user, 'Send discount code'));

            return;
        }
        if ($action === 'dd' && isset($parts[2])) {
            $txId = (int) $parts[2];
            $result = $this->checkout->clearDiscount($txId);
            if (! empty($result['ok'])) {
                $this->refreshCheckoutAfterDiscount($ctx, $user, $chatId, $txId);
            } else {
                $this->runtime->sendMessage($ctx, $chatId, '⛔ failed');
            }

            return;
        }
        if ($action === 'pm' && isset($parts[2], $parts[3])) {
            $cbId = (string) ($payload['callback_query_id'] ?? '');
            $this->dispatchDeferredC2c($user->id, $chatId, $ctx->platform, (int) $parts[2], (int) $parts[3], $cbId !== '' ? $cbId : null);

            return;
        }
        if ($action === 'sw' && isset($parts[2])) {
            $this->promptWalletPartial($ctx, $user, $chatId, (int) $parts[2]);

            return;
        }
        if ($action === 'swy' && isset($parts[2])) {
            $result = $this->checkout->payWithWallet($user, (int) $parts[2], true);
            $this->handleWalletResult($ctx, $user, $chatId, $result);

            return;
        }
        if ($action === 'swn' && isset($parts[2])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return;
        }
        if ($action === 'bw' && isset($parts[2])) {
            $txId = (int) $parts[2];
            $result = $this->checkout->sendBaleInvoice($ctx, $user, $chatId, $txId, $this->texts->getForUser('msg.buy.bale_invoice_title', $user, 'Purchase'));
            if (empty($result['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.bale_invoice_failed', $user, 'Bale payment failed'));
            }

            return;
        }
        if ($action === 'cr' && isset($parts[2])) {
            $link = $this->checkout->createCryptoPaymentLink($user, (int) $parts[2]);
            $this->runtime->sendMessage($ctx, $chatId, ! empty($link['ok'])
                ? ($this->texts->getForUser('msg.buy.crypto_link', $user, 'Pay:').' '.(string) ($link['link'] ?? ''))
                : '⛔ failed');

            return;
        }
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'buy_choose_traffic' && is_numeric($trimmed)) {
            $planId = (int) ($data['plan_id'] ?? 0);
            $plan = SvpPlan::query()->find($planId);
            $gb = $plan ? $this->planPricing->normalizeGb($plan, (int) $trimmed) : max(0, (int) $trimmed);
            if ($plan && $this->planPricing->planNeedsClientsPrompt($plan)) {
                $this->state->set($user, 'buy_choose_clients', ['plan_id' => $planId, 'extra_gb' => $gb]);
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.prompt_clients', $user, 'Send client count'));

                return;
            }
            $this->dispatchDeferredCheckout($user->id, $chatId, $ctx->platform, $planId, $gb, 1);
            $this->state->clear($user);

            return;
        }

        if ($state === 'buy_choose_clients' && is_numeric($trimmed)) {
            $planId = (int) ($data['plan_id'] ?? 0);
            $gb = (int) ($data['extra_gb'] ?? 0);
            $clients = max(1, (int) $trimmed);
            $this->dispatchDeferredCheckout($user->id, $chatId, $ctx->platform, $planId, $gb, $clients);
            $this->state->clear($user);

            return;
        }

        if ($state === 'buy_discount') {
            $txId = (int) ($data['transaction_id'] ?? 0);
            $result = $this->checkout->applyDiscountCode($txId, $trimmed, $user->id);
            if (! empty($result['ok'])) {
                $this->state->set($user, 'buy_checkout', [
                    'transaction_id' => $txId,
                    'plan_id' => (int) ($this->checkout->transactionMeta($txId)['plan_id'] ?? 0),
                ]);
                $this->refreshCheckoutAfterDiscount($ctx, $user, $chatId, $txId);
            } else {
                $this->state->set($user, 'buy_checkout', [
                    'transaction_id' => $txId,
                    'plan_id' => (int) ($this->checkout->transactionMeta($txId)['plan_id'] ?? 0),
                ]);
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.discount_invalid', $user, 'Invalid code'));
            }

            return;
        }

        if ($state === 'buy_checkout') {
            $txId = (int) ($data['transaction_id'] ?? 0);
            if ($this->isCancelText($trimmed, $user)) {
                $this->state->clear($user);
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

                return;
            }
            if ($txId > 0) {
                $meta = $this->checkout->transactionMeta($txId);
                $planId = (int) ($meta['plan_id'] ?? 0);
                $plan = SvpPlan::query()->find($planId);
                if ($plan) {
                    $amount = $this->checkout->transactionAmount($txId);
                    $this->sendCheckoutSummary($ctx, $user, $chatId, $plan, $amount, (int) ($meta['extra_gb'] ?? 0), $txId);
                }
            }
        }
    }

    protected function isCancelText(string $text, SvpUser $user): bool
    {
        return in_array(mb_strtolower($text), ['cancel', 'لغو', 'انصراف'], true);
    }

    public function refreshCheckoutAfterDiscount(BotContext $ctx, SvpUser $user, int $chatId, int $txId): void
    {
        $meta = $this->checkout->transactionMeta($txId);
        $planId = (int) ($meta['plan_id'] ?? 0);
        $plan = SvpPlan::query()->find($planId);
        if (! $plan) {
            return;
        }
        $amount = $this->checkout->transactionAmount($txId);
        $extraGb = (int) ($meta['extra_gb'] ?? 0);
        $this->state->set($user, 'buy_checkout', [
            'transaction_id' => $txId,
            'plan_id' => $planId,
            'extra_gb' => $extraGb,
            'amount' => $amount,
        ]);
        $this->sendCheckoutSummary($ctx, $user, $chatId, $plan, $amount, $extraGb, $txId);
    }

    public function deliverCardPayment(BotContext $ctx, SvpUser $user, int $chatId, int $txId, int $cardId, ?string $callbackQueryId = null): void
    {
        if ($callbackQueryId) {
            $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $callbackQueryId, 'ok' => true]);
        }
        $this->startCardPaymentForTx($ctx, $user, $chatId, $txId, $cardId);
    }

    protected function sendCheckoutSummary(BotContext $ctx, SvpUser $user, int $chatId, SvpPlan $plan, float $amount, int $extraGb, int $txId): void
    {
        $summary = $this->texts->format(
            $this->texts->getForUser('msg.buy.plan_checkout_summary', $user, "{name} — {amount}"),
            ['name' => $plan->name.($extraGb > 0 ? " +{$extraGb}GB" : ''), 'amount' => number_format($amount)]
        );
        $methods = $this->checkout->enabledPaymentMethods($ctx);
        $rows = [];
        if (in_array('c2c', $methods, true)) {
            $card = DB::table('svp_cards')->where('active', true)->orderBy('priority')->value('id');
            if ($card) {
                $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_c2c', $user, 'Card'), 'callback_data' => 'buy:pm:'.$txId.':'.$card]];
            }
        }
        if (in_array('site_wallet', $methods, true)) {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_wallet', $user, 'Wallet'), 'callback_data' => 'buy:sw:'.$txId]];
        }
        if (in_array('bale_wallet', $methods, true) && $ctx->platform === 'bale') {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_bale', $user, 'Bale'), 'callback_data' => 'buy:bw:'.$txId]];
        }
        if (in_array('crypto', $methods, true)) {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_crypto', $user, 'Crypto'), 'callback_data' => 'buy:cr:'.$txId]];
        }
        $meta = $this->checkout->transactionMeta($txId);
        $rows[] = [['text' => $this->texts->getForUser('btn.buy.discount', $user, 'Discount'), 'callback_data' => 'buy:dc:'.$txId]];
        if (! empty($meta['discount_code'])) {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.discount_remove', $user, 'Remove discount'), 'callback_data' => 'buy:dd:'.$txId]];
        }
        $rows[] = [['text' => $this->texts->getForUser('btn.buy.cancel', $user, 'Cancel'), 'callback_data' => 'buy:x:1']];
        $this->runtime->sendMessage($ctx, $chatId, $summary, ['reply_markup' => $this->keyboards->inline($rows)]);
    }

    protected function promptWalletPartial(BotContext $ctx, SvpUser $user, int $chatId, int $txId): void
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        $amount = (float) ($tx->amount ?? 0);
        $balance = (float) $user->balance;
        if ($balance >= $amount) {
            $result = $this->checkout->payWithWallet($user, $txId, false);
            $this->handleWalletResult($ctx, $user, $chatId, $result);

            return;
        }
        if ($balance <= 0) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.insufficient_balance', $user, 'Insufficient balance'));

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
            $this->texts->getForUser('msg.buy.wallet_partial_confirm', $user, 'Use {balance} from wallet? Remaining: {remaining}'),
            ['balance' => number_format($balance), 'remaining' => number_format(max(0, $amount - $balance))]
        ), [
            'reply_markup' => $this->keyboards->inline([
                [
                    ['text' => $this->texts->getForUser('btn.yes', $user, 'Yes'), 'callback_data' => 'buy:swy:'.$txId],
                    ['text' => $this->texts->getForUser('btn.no', $user, 'No'), 'callback_data' => 'buy:swn:'.$txId],
                ],
            ]),
        ]);
    }

    /** @param  array<string, mixed>  $result */
    protected function handleWalletResult(BotContext $ctx, SvpUser $user, int $chatId, array $result): void
    {
        $this->state->clear($user);
        if (! empty($result['ok']) && ! empty($result['partial'])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.wallet_partial_ok', $user, 'Partial wallet paid. Pay remainder via card.'));
            if (isset($result['remaining']) && (float) $result['remaining'] > 0) {
                $card = DB::table('svp_cards')->where('active', true)->orderBy('priority')->value('id');
                if ($card) {
                    $txId = (int) DB::table('svp_transactions')->where('user_id', $user->id)->orderByDesc('id')->value('id');
                    $this->startCardPaymentForTx($ctx, $user, $chatId, $txId, (int) $card);
                }
            }

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, ! empty($result['ok'])
            ? $this->texts->getForUser('msg.buy.wallet_paid', $user, 'Paid from wallet.')
            : $this->texts->getForUser('msg.buy.insufficient_balance', $user, 'Insufficient balance'));
    }

    protected function startCardPaymentForTx(BotContext $ctx, SvpUser $user, int $chatId, int $txId, int $cardId): void
    {
        $created = $this->checkout->createC2cReceipt($user, $txId, $cardId);
        if (empty($created['ok'])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.payment_failed', $user, 'Payment failed'));

            return;
        }
        $card = $created['card'] ?? null;
        $cardText = $card
            ? $this->texts->format(
                $this->texts->getForUser('msg.buy.card_details', $user, "Card: {number}\n{holder}"),
                ['number' => $card->card_number, 'holder' => $card->holder_name]
            )
            : $this->texts->getForUser('msg.buy.no_card', $user, 'No card configured');
        $this->state->set($user, 'awaiting_receipt_photo', [
            'receipt_id' => (int) ($created['receipt_id'] ?? 0),
            'transaction_id' => $txId,
            'chat_id' => $chatId,
            'platform' => $ctx->platform,
            'waiting_since' => now()->toIso8601String(),
        ]);
        $this->runtime->sendMessage($ctx, $chatId, $cardText."\n\n".$this->texts->getForUser('msg.buy.upload_receipt', $user));
    }

    /** @param  array<string, mixed>  $message */
    public function handleReceiptPhoto(BotContext $ctx, SvpUser $user, int $chatId, array $message): void
    {
        $data = $this->state->data($user);
        $receiptId = (int) ($data['receipt_id'] ?? 0);
        if ($receiptId < 1) {
            return;
        }
        $fileId = $message['photo'][0]['file_id'] ?? $message['document']['file_id'] ?? null;
        if ($fileId) {
            $path = storage_path('app/receipts/'.$receiptId.'.jpg');
            $this->runtime->downloadFileToPath($ctx, (string) $fileId, $path);
            DB::table('svp_receipts')->where('id', $receiptId)->update([
                'file_id' => $fileId,
                'file_path' => is_file($path) ? $path : null,
                'status' => 'pending',
            ]);
        }
        $this->state->clear($user);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.receipt_received', $user, 'Receipt received, pending review.'));
    }

    public function createTopupCheckout(BotContext $ctx, SvpUser $user, int $chatId, float $amount): void
    {
        $created = $this->checkout->createTopupTransaction($user, $ctx, $amount);
        if (empty($created['ok'])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.topup_failed', $user, 'Topup failed'));

            return;
        }
        $txId = (int) ($created['transaction_id'] ?? 0);
        if (in_array('crypto', $this->checkout->enabledPaymentMethods($ctx), true)) {
            $link = $this->checkout->createCryptoPaymentLink($user, $txId);
            if (! empty($link['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.crypto_link', $user, 'Pay:').' '.(string) ($link['link'] ?? ''));

                return;
            }
        }
        if ($ctx->platform === 'bale' && in_array('bale_wallet', $this->checkout->enabledPaymentMethods($ctx), true)) {
            $result = $this->checkout->sendBaleInvoice($ctx, $user, $chatId, $txId, $this->texts->getForUser('msg.wallet.bale_topup_title', $user, 'Wallet topup'));
            if (empty($result['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.bale_topup', $user, 'Use Bale wallet for topup'));
            }

            return;
        }
        $cardId = (int) (DB::table('svp_cards')->where('active', true)->orderBy('priority')->value('id') ?? 0);
        $this->startCardPaymentForTx($ctx, $user, $chatId, $txId, $cardId);
    }

    /** @param  array<string, mixed>  $preCheckout */
    public function handleBalePreCheckout(BotContext $ctx, array $preCheckout): void
    {
        $payload = (string) ($preCheckout['invoice_payload'] ?? '');
        $txId = str_starts_with($payload, 'tx:') ? (int) substr($payload, 3) : 0;
        $amount = (int) ($preCheckout['total_amount'] ?? 0);
        $ok = $txId > 0 && $this->checkout->validateBalePreCheckout($txId, $amount);
        $this->runtime->answerPreCheckoutQuery($ctx, [
            'pre_checkout_query_id' => (string) ($preCheckout['id'] ?? ''),
            'ok' => $ok,
            'error_message' => $ok ? '' : $this->texts->get('msg.buy.invalid_transaction', 'Invalid transaction'),
        ]);
    }

    /** @param  array<string, mixed>  $message */
    public function handleSuccessfulPayment(BotContext $ctx, array $message): void
    {
        $from = $message['from'] ?? [];
        $chatId = (int) ($message['chat']['id'] ?? 0);
        $user = app(\App\Modules\Core\Bot\Services\UserResolver::class)->resolve($ctx, $from);
        if (! $user) {
            return;
        }
        $payment = (array) ($message['successful_payment'] ?? []);
        $payload = (string) ($payment['invoice_payload'] ?? '');
        if (str_starts_with($payload, 'tx:')) {
            $txId = (int) substr($payload, 3);
            $result = $this->checkout->fulfillBalePayment($user, $txId);
            if (! empty($result['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.payment_ok', $user, 'Payment received.'));
            } else {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.payment_failed', $user, 'Payment failed'));
            }

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.payment_ok', $user, 'Payment received.'));
    }

    protected function dispatchDeferredCheckout(
        int $userId,
        int $chatId,
        string $platform,
        int $planId,
        int $extraGb = 0,
        int $clientsCount = 1,
    ): void {
        $this->deferredCache->putCheckout($userId, $chatId, $platform, $planId, $extraGb, $clientsCount);
        DeferredPurchaseCheckoutJob::dispatch($userId, $chatId, $platform, $planId, $extraGb, $clientsCount);
    }

    protected function dispatchDeferredC2c(
        int $userId,
        int $chatId,
        string $platform,
        int $txId,
        int $cardId,
        ?string $cbId = null,
    ): void {
        $this->deferredCache->putC2c($userId, $chatId, $platform, $txId, $cardId, $cbId);
        DeferredC2cInvoiceJob::dispatch($userId, $chatId, $platform, $txId, $cardId, $cbId);
    }
}
