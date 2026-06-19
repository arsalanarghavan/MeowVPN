<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Services\SettingsStore;

class ForceJoinGate
{
    public function __construct(
        protected SettingsStore $settings,
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function shouldBlock(
        BotContext $ctx,
        int $fromId,
        int $chatId,
        ?SvpUser $user,
        string $cmd = '',
        string $cbData = '',
    ): bool {
        if (! $this->settings->get('force_join_enabled', false)) {
            return false;
        }

        if ($cmd === 'start' || str_starts_with($cbData, 'chjoin:')) {
            return false;
        }

        $channelId = (string) $this->settings->get('force_join_channel_id', '');
        if ($channelId === '') {
            return false;
        }

        if ($this->isMember($ctx, $channelId, $fromId)) {
            return false;
        }

        $url = (string) $this->settings->get('force_join_channel_url', '');
        $msg = $this->texts->get('msg.force_join', 'Join our channel to continue');
        $extra = [];
        if ($url !== '') {
            $extra['reply_markup'] = [
                'inline_keyboard' => [[
                    ['text' => $this->texts->get('btn.force_join', 'Join'), 'url' => $url],
                ]],
            ];
        }
        $this->runtime->sendMessage($ctx, $chatId, $msg, $extra);

        return true;
    }

    protected function isMember(BotContext $ctx, string $channelId, int $userId): bool
    {
        $r = $this->runtime->client($ctx)?->getChatMember([
            'chat_id' => $channelId,
            'user_id' => $userId,
        ]);
        if (! is_array($r) || empty($r['ok'])) {
            return false;
        }
        $status = (string) ($r['result']['status'] ?? '');

        return in_array($status, ['creator', 'administrator', 'member', 'restricted'], true);
    }
}
