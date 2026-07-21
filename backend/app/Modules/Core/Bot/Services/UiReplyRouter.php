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
        protected UiLayoutService $layout,
        protected KeyboardBuilder $keyboards,
        protected BotRuntime $runtime,
    ) {}

    public function routeMainMenuText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trim = trim($text);
        if ($trim === '') {
            return false;
        }

        if ($this->layout->isUserBackToMainText($trim, $user)) {
            $this->runtime->sendMessage($ctx, $chatId, '', [
                'reply_markup' => $this->keyboards->userMainReply($user),
            ]);

            return true;
        }

        $subSurface = $this->layout->matchCustomHubSubmenuSurface($trim, $user, 'user');
        if ($subSurface !== null && $subSurface !== '') {
            $markup = $this->layout->buildUserCustomSubmenuWithBack($subSurface, $user);
            if ($markup !== null) {
                $this->runtime->sendMessage($ctx, $chatId, '', ['reply_markup' => $markup]);

                return true;
            }
        }

        $actionId = $this->layout->matchUserReplyAction($trim, $user, null);
        if ($actionId !== null && $this->dispatchUserMemberAction($actionId, $ctx, $user, $chatId)) {
            return true;
        }

        return $this->routeLegacyMainMenuText($ctx, $user, $chatId, $trim);
    }

    public function dispatchUserMemberAction(string $actionId, BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        $def = UiActionRegistryService::get($actionId);
        if (is_array($def['route'] ?? null) && isset($def['route']['user_main'])) {
            return $this->dispatchUserMainRoute($ctx, $user, $chatId, (string) $def['route']['user_main']);
        }

        return match ($actionId) {
            'user.main.buy' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'buy'),
            'user.main.manage' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'manage'),
            'user.main.wallet' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'wallet'),
            'user.main.account' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'account'),
            'user.main.support' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'support'),
            'user.main.apps' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'apps'),
            'user.main.referral' => $this->dispatchUserMainRoute($ctx, $user, $chatId, 'referral'),
            default => false,
        };
    }

    protected function dispatchUserMainRoute(BotContext $ctx, SvpUser $user, int $chatId, string $route): bool
    {
        return match ($route) {
            'buy' => $this->userMenu->showBuy($ctx, $user, $chatId) || true,
            'manage' => $this->userMenu->showManage($ctx, $user, $chatId) || true,
            'wallet' => $this->userMenu->showWallet($ctx, $user, $chatId) || true,
            'account' => $this->userMenu->showAccount($ctx, $user, $chatId) || true,
            'support' => $this->userMenu->showSupport($ctx, $user, $chatId) || true,
            'apps' => $this->userMenu->showApps($ctx, $user, $chatId) || true,
            'referral' => $this->userMenu->showReferral($ctx, $user, $chatId) || true,
            default => false,
        };
    }

    protected function routeLegacyMainMenuText(BotContext $ctx, SvpUser $user, int $chatId, string $trim): bool
    {
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
