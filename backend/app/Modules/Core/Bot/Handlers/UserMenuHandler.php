<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;

class UserMenuHandler
{
    public function __construct(
        protected BuyHandler $buy,
        protected ServiceHandler $service,
        protected WalletHandler $wallet,
        protected AccountHandler $account,
        protected SupportHandler $support,
        protected AppsHandler $apps,
        protected ReferralHandler $referral,
        protected \App\Modules\Core\Bot\Services\KeyboardBuilder $keyboards,
        protected \App\Modules\Core\Bot\Services\BotRuntime $runtime,
        protected \App\Modules\Core\Bot\Services\TextService $texts,
    ) {}

    public function showMainMenu(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.main_menu', $user, 'Main menu'), [
            'reply_markup' => $this->keyboards->userMainReply($user),
        ]);
    }

    public function showBuy(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->buy->showBuyEntry($ctx, $user, $chatId);
    }

    public function showManage(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->service->listServices($ctx, $user, $chatId);
    }

    public function showWallet(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->wallet->showWallet($ctx, $user, $chatId);
    }

    public function showAccount(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->account->showAccount($ctx, $user, $chatId);
    }

    public function showSupport(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->support->showSupport($ctx, $user, $chatId);
    }

    public function showApps(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->apps->showApps($ctx, $user, $chatId);
    }

    public function showReferral(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->referral->showReferral($ctx, $user, $chatId);
    }
}
