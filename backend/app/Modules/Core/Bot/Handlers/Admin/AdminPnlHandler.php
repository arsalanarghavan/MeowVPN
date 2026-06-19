<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\Log;

/**
 * Legacy fallback for unhandled pnl:* callbacks (WP parity shim).
 *
 * All known admin inline routes are wired in {@see AdminHandlerRegistry::handlePnl}.
 * Intentional no-ops elsewhere: `noop` and `alnoop:*` dismiss buttons ({@see CallbackHandler}).
 */
class AdminPnlHandler extends AbstractAdminHandler
{
    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.pnl', $user, '📊 PnL');
    }

    /** @param  array<int, string>  $parts */
    public function handle(BotContext $ctx, array $parts, ?SvpUser $user, int $chatId, int $msgId, int $fromId): void
    {
        if (! $user) {
            return;
        }
        Log::warning('bot.admin.pnl.unknown_callback', [
            'parts' => $parts,
            'user_id' => $user->id,
            'chat_id' => $chatId,
        ]);
        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.unknown_cmd', $user, 'Unknown callback'));
    }
}
