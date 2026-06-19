<?php

namespace App\Modules\Core\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Handlers\CallbackHandler;
use App\Modules\Core\Bot\Handlers\StartHandler;
use App\Modules\Core\Bot\Handlers\SyncHandler;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\ForceJoinGate;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\UiReplyRouter;
use App\Modules\Core\Bot\Services\UserResolver;
use App\Services\SettingsStore;

class UpdateRouter
{
    public function __construct(
        protected SettingsStore $settings,
        protected UserResolver $users,
        protected ForceJoinGate $forceJoin,
        protected AdminGuard $adminGuard,
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected BotStateService $state,
        protected UiReplyRouter $uiReply,
        protected StartHandler $startHandler,
        protected CallbackHandler $callbackHandler,
        protected BuyHandler $buyHandler,
        protected SyncHandler $syncHandler,
    ) {}

    /** @param  array<string, mixed>  $update */
    public function dispatch(BotContext $ctx, array $update): void
    {
        if (! $this->settings->get('bot_enabled', true)) {
            return;
        }

        if ($ctx->platform === 'bale' && ! empty($update['pre_checkout_query'])) {
            $this->buyHandler->handleBalePreCheckout($ctx, $update['pre_checkout_query']);

            return;
        }

        if ($ctx->platform === 'bale' && ! empty($update['message']['successful_payment'])) {
            $this->buyHandler->handleSuccessfulPayment($ctx, $update['message']);

            return;
        }

        [$from, $chat, $text, $cb] = $this->extractMessageParts($update);
        if (! $from || ! $chat) {
            return;
        }

        $fromId = (int) ($from['id'] ?? 0);
        $chatId = (int) ($chat['id'] ?? 0);
        if ($fromId < 1 || $chatId < 1) {
            return;
        }

        $user = $this->users->resolve($ctx, $from);
        $cmd = '';
        if ($text && preg_match('#^/([a-zA-Z0-9_]+)#u', $text, $m)) {
            $cmd = strtolower($m[1]);
        }
        $cbData = (string) ($cb['data'] ?? '');

        if ($this->forceJoin->shouldBlock($ctx, $fromId, $chatId, $user, $cmd, $cbData)) {
            return;
        }

        if ($cb) {
            if (! $this->callbackBypassesUserGate($cbData)) {
                if (! $user) {
                    $this->runtime->sendMessage($ctx, $chatId, $this->texts->get('msg.start_first', 'Please send /start'));

                    return;
                }

                if ((string) $user->status === 'blocked') {
                    $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.blocked', $user));

                    return;
                }

                if ($ctx->platform === 'telegram' && in_array((string) $user->status, ['pending', 'rejected'], true)) {
                    $user->status = 'approved';
                    $user->approved_by = $user->approved_by ?: 'auto:telegram';
                    $user->approved_at = $user->approved_at ?? now();
                    $user->save();
                }

                if (in_array((string) $user->status, ['pending', 'rejected'], true)) {
                    $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.approval_wait', $user));

                    return;
                }
            }

            $this->callbackHandler->handle($ctx, [
                'cb' => $cb,
                'user' => $user,
                'chat_id' => $chatId,
                'from' => $from,
            ]);

            return;
        }

        if ($cmd === 'start') {
            $this->startHandler->handle($ctx, [
                'chat_id' => $chatId,
                'from' => $from,
                'user' => $user,
                'text' => $text,
            ]);

            return;
        }

        if ($cmd === 'lang' && $user) {
            $this->startHandler->handleLang($ctx, $user, $chatId, $text);

            return;
        }

        if ($cmd === 'panel') {
            $this->startHandler->handlePanel($ctx, $user, $fromId, $chatId);

            return;
        }

        if (! $user) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->get('msg.start_first', 'Please send /start'));

            return;
        }

        if ((string) $user->status === 'blocked') {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.blocked', $user));

            return;
        }

        if ($ctx->platform === 'telegram' && in_array((string) $user->status, ['pending', 'rejected'], true)) {
            $user->status = 'approved';
            $user->approved_by = $user->approved_by ?: 'auto:telegram';
            $user->approved_at = $user->approved_at ?? now();
            $user->save();
        }

        if (in_array((string) $user->status, ['pending', 'rejected'], true)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.approval_wait', $user));

            return;
        }

        $state = $this->state->get($user);

        $message = is_array($update['message'] ?? null) ? $update['message'] : null;
        $userStateRouter = app(\App\Modules\Core\Bot\Services\UserStateRouter::class);
        if ($userStateRouter->route($ctx, $user, $chatId, $text, $message)) {
            return;
        }

        if ($user && (int) $user->admin_mode && $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            $stateRouter = app(\App\Modules\Core\Bot\Services\AdminStateRouter::class);
            if ($stateRouter->route($ctx, $user, $chatId, $text, $update, $from)) {
                return;
            }
        }

        if ($this->uiReply->routeMainMenuText($ctx, $user, $chatId, trim((string) $text))) {
            return;
        }

        if ((int) $user->admin_mode && $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            $this->callbackHandler->handleAdminText($ctx, $user, $chatId, trim((string) $text), $from);
        }
    }

    /**
     * @param  array<string, mixed>  $update
     * @return array{0: ?array, 1: ?array, 2: ?string, 3: ?array}
     */
    protected function extractMessageParts(array $update): array
    {
        if (! empty($update['callback_query']) && is_array($update['callback_query'])) {
            $cb = $update['callback_query'];
            $from = $cb['from'] ?? null;
            $msg = $cb['message'] ?? [];
            $chat = is_array($msg) ? ($msg['chat'] ?? null) : null;

            return [$from, $chat, null, $cb];
        }

        if (! empty($update['message']) && is_array($update['message'])) {
            $m = $update['message'];

            return [$m['from'] ?? null, $m['chat'] ?? null, isset($m['text']) ? (string) $m['text'] : null, null];
        }

        return [null, null, null, null];
    }

    protected function callbackBypassesUserGate(string $cbData): bool
    {
        if ($cbData === 'noop' || str_starts_with($cbData, 'alnoop:') || str_starts_with($cbData, 'chjoin:')) {
            return true;
        }

        return str_starts_with($cbData, 'reg:')
            || str_starts_with($cbData, 'rc:')
            || str_starts_with($cbData, 'pnl:');
    }
}
