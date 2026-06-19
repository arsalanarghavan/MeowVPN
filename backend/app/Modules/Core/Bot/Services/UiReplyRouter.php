<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\UserMenuHandler;

class UiReplyRouter
{
    public function __construct(
        protected TextService $texts,
        protected UserMenuHandler $userMenu,
    ) {}

    public function routeMainMenuText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trim = trim($text);
        if ($trim === '') {
            return false;
        }

        $buy = $this->texts->getForUser('btn.main.buy', $user);
        $manage = $this->texts->getForUser('btn.main.manage', $user);
        $wallet = $this->texts->getForUser('btn.main.wallet', $user);
        $account = $this->texts->getForUser('btn.main.account', $user);
        $support = $this->texts->getForUser('btn.main.support', $user);
        $apps = $this->texts->getForUser('btn.main.apps', $user);
        $referral = $this->texts->getForUser('btn.main.referral', $user);

        return match ($trim) {
            $buy => $this->userMenu->showBuy($ctx, $user, $chatId) || true,
            $manage => $this->userMenu->showManage($ctx, $user, $chatId) || true,
            $wallet => $this->userMenu->showWallet($ctx, $user, $chatId) || true,
            $account => $this->userMenu->showAccount($ctx, $user, $chatId) || true,
            $support => $this->userMenu->showSupport($ctx, $user, $chatId) || true,
            $apps => $this->userMenu->showApps($ctx, $user, $chatId) || true,
            $referral => $this->userMenu->showReferral($ctx, $user, $chatId) || true,
            default => false,
        };
    }
}
