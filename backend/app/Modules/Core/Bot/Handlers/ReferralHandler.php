<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class ReferralHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected SettingsStore $settings,
    ) {}

    public function showReferral(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $botName = (string) $this->settings->get('telegram_bot_username', 'bot');
        if ($ctx->platform === 'bale') {
            $botName = (string) $this->settings->get('bale_bot_username', $botName);
            $link = $botName !== '' ? "https://ble.ir/{$botName}?start=ref_{$user->id}" : '—';
        } else {
            $link = $botName !== '' ? "https://t.me/{$botName}?start=ref_{$user->id}" : '—';
        }
        $invites = (int) DB::table('svp_users')->where('invited_by', $user->id)->count();
        $earnings = (float) DB::table('svp_transactions as t')
            ->join('svp_users as u', 'u.id', '=', 't.user_id')
            ->where('u.invited_by', $user->id)
            ->where('t.status', 'completed')
            ->sum('t.referral_amount');
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.referral.link', $user, "Your link: {link}\nInvites: {count}\nEarnings: {earnings}"),
            ['link' => $link, 'count' => $invites, 'earnings' => number_format($earnings)]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg);
    }
}
