<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;

class ForceJoinGate
{
    public function __construct(
        protected RequiredChannelService $requiredChannel,
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
        $plat = $this->requiredChannel->normalizePlatform($ctx->platform);

        if (! $this->requiredChannel->shouldGate($plat)) {
            return false;
        }

        if ($cmd === 'start' || str_starts_with($cbData, 'chjoin:')) {
            return false;
        }

        // Interactive path: cache hit only; miss fail-open + background refresh (WP router ~522-531).
        if ($this->requiredChannel->gateAllowsInteractive($ctx, $fromId)) {
            return false;
        }

        $this->requiredChannel->sendPrompt($ctx, $chatId, $user);

        return true;
    }

    public function shouldGate(string $platform): bool
    {
        return $this->requiredChannel->shouldGate($platform);
    }

    public function isEnabled(string $platform): bool
    {
        return $this->requiredChannel->isEnabled($platform);
    }

    public function sendPrompt(BotContext $ctx, int $chatId, ?SvpUser $user = null): void
    {
        $this->requiredChannel->sendPrompt($ctx, $chatId, $user);
    }

    /** @return array{ok:bool, message?:string, message_id?:int} */
    public function publishAnnouncement(string $platform): array
    {
        return $this->requiredChannel->publishAnnouncement($platform);
    }

    public function handleVerifyCallback(
        BotContext $ctx,
        int $fromId,
        int $chatId,
        string $cbId,
        ?SvpUser $user,
    ): void {
        $ok = $this->requiredChannel->userPasses($ctx, $fromId, true);
        if (! $ok) {
            usleep(300000);
            $ok = $this->requiredChannel->userPasses($ctx, $fromId, true);
        }

        if ($cbId !== '') {
            $success = $user
                ? $this->texts->getForUser('msg.force_join.success', $user, 'Membership verified.')
                : $this->texts->get('msg.force_join.success', 'Membership verified.');
            $fail = $user
                ? $this->texts->getForUser('msg.force_join.fail', $user, 'You are not in the channel yet.')
                : $this->texts->get('msg.force_join.fail', 'You are not in the channel yet.');
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => $ok ? $success : $fail,
                'show_alert' => ! $ok,
            ]);
        }

        if (! $ok) {
            $this->requiredChannel->sendPrompt($ctx, $chatId, $user);

            return;
        }

        if ($user) {
            $this->requiredChannel->onVerifySuccess($ctx, $chatId, $user);
        } else {
            $this->runtime->sendMessage(
                $ctx,
                $chatId,
                $this->texts->get('msg.force_join.success', 'Membership verified.')
                ."\n".$this->texts->get('msg.start_first', 'Please send /start')
            );
        }
    }
}
