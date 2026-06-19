<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class AppsHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    public function showApps(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.apps.list', $user, 'Client apps'));
    }
}
