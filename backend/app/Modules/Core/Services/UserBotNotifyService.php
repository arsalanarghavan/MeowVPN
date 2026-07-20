<?php

namespace App\Modules\Core\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Telegram\Services\TelegramMirrorBotService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserBotNotifyService
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TelegramMirrorBotService $mirrors,
    ) {}

    public function sendToUser(SvpUser $user, string $text, string $channel = 'both', int $resellerOwnerId = 0): void
    {
        $profile = $this->resellerProfile($resellerOwnerId);
        $sendTg = in_array($channel, ['both', 'telegram'], true);
        $sendBl = in_array($channel, ['both', 'bale'], true);

        if ($sendTg && (int) ($user->tg_user_id ?? 0) > 0) {
            $ctx = $this->telegramContextForUser($user, $resellerOwnerId, $profile);
            $this->runtime->sendMessage($ctx, (int) $user->tg_user_id, $text);
        }
        if ($sendBl && (int) ($user->bale_user_id ?? 0) > 0) {
            $ctx = new BotContext('bale', $resellerOwnerId, $profile);
            $this->runtime->sendMessage($ctx, (int) $user->bale_user_id, $text);
        }
    }

    /** @param  array<string, mixed>|null  $profile */
    protected function telegramContextForUser(SvpUser $user, int $resellerOwnerId, ?array $profile): BotContext
    {
        if ($resellerOwnerId > 0) {
            return new BotContext('telegram', $resellerOwnerId, $profile);
        }

        $mid = (int) ($user->last_tg_mirror_bot_id ?? 0);
        if ($mid > 0) {
            $mirrorProfile = $this->mirrors->profileArrayForRuntime($mid);
            $bot = $this->mirrors->find($mid);
            if ($mirrorProfile && $bot && $bot->enabled && $this->mirrors->tokenFor($bot) !== '') {
                return new BotContext('telegram', 0, null, $mid, $mirrorProfile);
            }
        }

        return new BotContext('telegram');
    }

    /** @return array<string, mixed>|null */
    protected function resellerProfile(int $resellerOwnerId): ?array
    {
        if ($resellerOwnerId < 1 || ! Schema::hasTable('svp_reseller_bot_profiles')) {
            return null;
        }

        $row = DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', $resellerOwnerId)
            ->first();

        return $row ? (array) $row : null;
    }
}
