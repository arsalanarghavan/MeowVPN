<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;

class KeyboardBuilder
{
    public function __construct(
        protected TextService $texts,
        protected UiLayoutService $layout,
    ) {}

    /** @return array<string, mixed> */
    public function userMainReply(?SvpUser $user = null): array
    {
        $custom = $this->layout->buildReplyKeyboard('user_main', $user);
        if ($custom !== null) {
            return $custom;
        }

        $t = fn (string $key) => $user
            ? $this->texts->getForUser($key, $user)
            : $this->texts->get($key);

        return [
            'keyboard' => [
                [['text' => $t('btn.main.buy')], ['text' => $t('btn.main.manage')]],
                [['text' => $t('btn.main.apps')], ['text' => $t('btn.main.support')]],
                [['text' => $t('btn.main.account')], ['text' => $t('btn.main.wallet')]],
                [['text' => $t('btn.main.referral')]],
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function inline(array $rows): array
    {
        return ['inline_keyboard' => $rows];
    }
}
