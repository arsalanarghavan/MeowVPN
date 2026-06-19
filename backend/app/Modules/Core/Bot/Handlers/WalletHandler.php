<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\BotCommerceCheckoutService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class WalletHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
        protected KeyboardBuilder $keyboards,
        protected BotCommerceCheckoutService $checkout,
    ) {}

    public function showWallet(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.wallet.balance', $user, 'Balance: {balance}'),
            ['balance' => number_format((float) $user->balance)]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg, [
            'reply_markup' => $this->keyboards->inline([
                [['text' => $this->texts->getForUser('btn.wallet.topup', $user, 'Top up'), 'callback_data' => 'wal:tu']],
                [['text' => $this->texts->getForUser('btn.wallet.crypto_topup', $user, 'Crypto topup'), 'callback_data' => 'wal:cr']],
                [['text' => $this->texts->getForUser('btn.wallet.history', $user, 'History'), 'callback_data' => 'wal:h:0']],
            ]),
        ]);
    }

    public function showHistoryFiltered(BotContext $ctx, SvpUser $user, int $chatId, string $filter, int $offset = 0): void
    {
        $off = max(0, $offset);
        $q = DB::table('svp_transactions')->where('user_id', $user->id)->orderByDesc('id');
        if ($filter === 'purchase') {
            $q->where('type', 'purchase');
        } elseif ($filter === 'topup') {
            $q->where('type', 'wallet_topup');
        }
        $total = (int) $q->count();
        $rows = (clone $q)->offset($off)->limit(10)->get();
        $lines = [$this->texts->getForUser('msg.wallet.history_title', $user, 'History')];
        if ($filter !== 'all') {
            $lines[0] .= ' ['.$filter.']';
        }
        if ($rows->isEmpty()) {
            $lines[] = $this->texts->getForUser('msg.wallet.history_empty', $user, '—');
        } else {
            foreach ($rows as $row) {
                $lines[] = '#'.(int) $row->id.' '.(string) $row->type.': '.number_format((float) $row->amount).' ('.(string) $row->status.')';
            }
        }
        $ik = [
            [
                ['text' => $this->texts->getForUser('btn.wallet.filter_purchase', $user, 'Purchases'), 'callback_data' => 'wal:f:purchase:0'],
                ['text' => $this->texts->getForUser('btn.wallet.filter_topup', $user, 'Topups'), 'callback_data' => 'wal:f:topup:0'],
            ],
            [['text' => $this->texts->getForUser('btn.wallet.filter_all', $user, 'All'), 'callback_data' => 'wal:f:all:0']],
        ];
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'wal:f:'.$filter.':'.max(0, $off - 10)];
        }
        if ($total > $off + 10) {
            $nav[] = ['text' => '▶', 'callback_data' => 'wal:f:'.$filter.':'.($off + 10)];
        }
        if ($nav !== []) {
            $ik[] = $nav;
        }
        $this->runtime->sendMessage($ctx, $chatId, implode("\n", $lines), ['reply_markup' => $this->keyboards->inline($ik)]);
    }

    public function beginCryptoTopup(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        if (! in_array('crypto', $this->checkout->enabledPaymentMethods($ctx), true)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.crypto_disabled', $user, 'Crypto topup unavailable'));

            return;
        }
        $this->state->set($user, 'wallet_topup_amount', ['crypto' => true]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.topup_prompt', $user, 'Enter amount:'));
    }

    public function beginTopup(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->state->set($user, 'wallet_topup_amount', []);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.topup_prompt', $user, 'Enter amount:'));
    }

    public function showHistory(BotContext $ctx, SvpUser $user, int $chatId, int $offset = 0): void
    {
        $off = max(0, $offset);
        $q = DB::table('svp_transactions')->where('user_id', $user->id)->orderByDesc('id');
        $total = (int) $q->count();
        $rows = (clone $q)->offset($off)->limit(10)->get();
        $lines = [$this->texts->getForUser('msg.wallet.history_title', $user, 'History')];
        if ($rows->isEmpty()) {
            $lines[] = $this->texts->getForUser('msg.wallet.history_empty', $user, '—');
        } else {
            foreach ($rows as $row) {
                $lines[] = '#'.(int) $row->id.' '.(string) $row->type.': '.number_format((float) $row->amount).' ('.(string) $row->status.')';
            }
        }
        $ik = [];
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'wal:h:'.max(0, $off - 10)];
        }
        if ($total > $off + 10) {
            $nav[] = ['text' => '▶', 'callback_data' => 'wal:h:'.($off + 10)];
        }
        if ($nav !== []) {
            $ik[] = $nav;
        }
        $this->runtime->sendMessage($ctx, $chatId, implode("\n", $lines), $ik !== [] ? ['reply_markup' => $this->keyboards->inline($ik)] : []);
    }

    public function handleTopupState(BotContext $ctx, SvpUser $user, int $chatId, string $raw): void
    {
        $raw = trim(str_replace([',', '٬'], '.', $raw));
        if ($raw === '' || in_array(mb_strtolower($raw), ['لغو', 'انصراف', 'cancel'], true)) {
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return;
        }
        if (! is_numeric($raw)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.topup_invalid', $user, 'Invalid amount'));

            return;
        }
        $amt = round((float) $raw, 2);
        if ($amt <= 0) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.wallet.topup_invalid', $user, 'Invalid amount'));

            return;
        }
        $data = $this->state->data($user);
        $this->state->clear($user);
        if (! empty($data['crypto'])) {
            $created = $this->checkout->createTopupTransaction($user, $ctx, $amt);
            if (empty($created['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, '⛔ topup failed');

                return;
            }
            $txId = (int) ($created['transaction_id'] ?? 0);
            $link = $this->checkout->createCryptoPaymentLink($user, $txId);
            $this->runtime->sendMessage($ctx, $chatId, ! empty($link['ok'])
                ? ($this->texts->getForUser('msg.buy.crypto_link', $user, 'Pay:').' '.(string) ($link['link'] ?? ''))
                : '⛔ failed');

            return;
        }
        app(BuyHandler::class)->createTopupCheckout($ctx, $user, $chatId, $amt);
    }
}
