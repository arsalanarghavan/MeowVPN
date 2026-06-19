<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Services\SettingsStore;

class UiLayoutService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, mixed>|null */
    public function buildReplyKeyboard(string $layoutKey, ?SvpUser $user): ?array
    {
        $layout = $this->settings->get('bot_ui_layout', []);
        if (! is_array($layout) || ! isset($layout[$layoutKey])) {
            return null;
        }

        $rows = $layout[$layoutKey];
        if (! is_array($rows)) {
            return null;
        }

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }
}
