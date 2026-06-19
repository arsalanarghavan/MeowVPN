<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class AccountHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function showAccount(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.account.summary', $user, "ID: {id}\nUser: @{username}\nStatus: {status}\nBalance: {balance}\nLanguage: {lang}"),
            [
                'id' => $user->id,
                'username' => $user->username ?: '—',
                'status' => $user->status,
                'balance' => number_format((float) $user->balance),
                'lang' => (string) ($user->bot_locale ?: '—'),
            ]
        );
        $this->runtime->sendMessage($ctx, $chatId, $msg, [
            'reply_markup' => [
                'inline_keyboard' => [
                    [
                        ['text' => $this->texts->getForUser('btn.account.sync_generate', $user, 'Generate sync code'), 'callback_data' => 'sync:g'],
                        ['text' => $this->texts->getForUser('btn.account.sync', $user, 'Enter sync code'), 'callback_data' => 'sync:i'],
                    ],
                ],
            ],
        ]);
    }
}
