<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Handlers\ServiceHandler;
use App\Modules\Core\Bot\Handlers\SyncHandler;
use App\Modules\Core\Bot\Handlers\WalletHandler;

class UserStateRouter
{
    public function __construct(
        protected BotStateService $state,
        protected BuyHandler $buy,
        protected ServiceHandler $service,
        protected WalletHandler $wallet,
        protected SyncHandler $sync,
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function route(BotContext $ctx, SvpUser $user, int $chatId, ?string $text, ?array $message = null): bool
    {
        $state = $this->state->get($user);
        if ($state === '') {
            return false;
        }

        $trimmed = trim((string) $text);

        if ($state === 'awaiting_receipt_photo' && is_array($message)) {
            $this->buy->handleReceiptPhoto($ctx, $user, $chatId, $message);

            return true;
        }

        if (str_starts_with($state, 'buy_')) {
            $this->buy->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'service_')) {
            $this->service->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'wallet_topup_amount') {
            $this->wallet->handleTopupState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'awaiting_sync_code') {
            $this->sync->handleCode($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'awaiting_receipt_photo' && $trimmed !== '') {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.upload_receipt', $user, 'Please send receipt photo'));

            return true;
        }

        if (in_array($state, ['service_checkout', 'service_discount'], true)) {
            $this->service->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($this->isCancelText($trimmed, $user)) {
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return true;
        }

        return false;
    }

    protected function isCancelText(string $text, SvpUser $user): bool
    {
        return in_array(mb_strtolower($text), ['cancel', 'لغو', 'انصراف'], true);
    }
}
