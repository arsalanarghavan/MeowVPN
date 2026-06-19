<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Services\SettingsStore;

class AdminNotifyService
{
    public function __construct(
        protected BotRuntime $runtime,
        protected SettingsStore $settings,
        protected AdminGuard $adminGuard,
    ) {}

    public function notifyAdmins(string $message): void
    {
        $tgIds = $this->adminGuard->platformAdminIds('telegram');
        $blIds = $this->adminGuard->platformAdminIds('bale');

        if ($tgIds !== [] && trim((string) $this->settings->get('telegram_token', '')) !== '') {
            $ctx = new BotContext('telegram');
            foreach ($tgIds as $chatId) {
                if ($chatId > 0) {
                    $this->runtime->sendMessage($ctx, $chatId, $message);
                    usleep(200000);
                }
            }
        }

        if ($blIds !== [] && trim((string) $this->settings->get('bale_token', '')) !== '') {
            $ctx = new BotContext('bale');
            $blText = str_replace(['**', '__'], '', $message);
            foreach ($blIds as $chatId) {
                if ($chatId > 0) {
                    $this->runtime->sendMessage($ctx, $chatId, $blText);
                    usleep(200000);
                }
            }
        }
    }
}
