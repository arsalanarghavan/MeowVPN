<?php

namespace App\Modules\Core\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserBotNotifyService
{
    public function __construct(protected BotRuntime $runtime) {}

    public function sendToUser(SvpUser $user, string $text, string $channel = 'both', int $resellerOwnerId = 0): void
    {
        $profile = $this->resellerProfile($resellerOwnerId);
        $sendTg = in_array($channel, ['both', 'telegram'], true);
        $sendBl = in_array($channel, ['both', 'bale'], true);

        if ($sendTg && (int) ($user->tg_user_id ?? 0) > 0) {
            $ctx = new BotContext('telegram', $resellerOwnerId, $profile);
            $this->runtime->sendMessage($ctx, (int) $user->tg_user_id, $text);
        }
        if ($sendBl && (int) ($user->bale_user_id ?? 0) > 0) {
            $ctx = new BotContext('bale', $resellerOwnerId, $profile);
            $this->runtime->sendMessage($ctx, (int) $user->bale_user_id, $text);
        }
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
