<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\DB;

class BotCheckoutUiService
{
    public function __construct(
        protected BotCommerceCheckoutService $checkout,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
        protected BotRuntime $runtime,
    ) {}

    /** @return array<string, mixed> */
    public function buildCheckoutKeyboard(BotContext $ctx, SvpUser $user, int $txId, string $prefix = 'buy'): array
    {
        $methods = $this->checkout->enabledPaymentMethods($ctx);
        $rows = [];
        if (in_array('c2c', $methods, true)) {
            $card = DB::table('svp_cards')->where('active', true)->orderBy('priority')->value('id');
            if ($card) {
                $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_c2c', $user, 'Card'), 'callback_data' => $prefix.':pm:'.$txId.':'.$card]];
            }
        }
        if (in_array('site_wallet', $methods, true)) {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_wallet', $user, 'Wallet'), 'callback_data' => $prefix.':sw:'.$txId]];
        }
        if (in_array('bale_wallet', $methods, true) && $ctx->platform === 'bale') {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_bale', $user, 'Bale'), 'callback_data' => $prefix.':bw:'.$txId]];
        }
        if (in_array('crypto', $methods, true)) {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.pay_crypto', $user, 'Crypto'), 'callback_data' => $prefix.':cr:'.$txId]];
        }
        $meta = $this->checkout->transactionMeta($txId);
        $rows[] = [['text' => $this->texts->getForUser('btn.buy.discount', $user, 'Discount'), 'callback_data' => $prefix.':dc:'.$txId]];
        if (! empty($meta['discount_code'])) {
            $rows[] = [['text' => $this->texts->getForUser('btn.buy.discount_remove', $user, 'Remove discount'), 'callback_data' => $prefix.':dd:'.$txId]];
        }
        $rows[] = [['text' => $this->texts->getForUser('btn.buy.cancel', $user, 'Cancel'), 'callback_data' => $prefix.':x:1']];

        return $this->keyboards->inline($rows);
    }

    public function sendCheckoutSummary(BotContext $ctx, SvpUser $user, int $chatId, string $title, float $amount, int $txId, string $prefix = 'buy'): void
    {
        $summary = $this->texts->format(
            $this->texts->getForUser('msg.buy.plan_checkout_summary', $user, "{name} — {amount}"),
            ['name' => $title, 'amount' => number_format($amount)]
        );
        $this->runtime->sendMessage($ctx, $chatId, $summary, [
            'reply_markup' => $this->buildCheckoutKeyboard($ctx, $user, $txId, $prefix),
        ]);
    }
}
