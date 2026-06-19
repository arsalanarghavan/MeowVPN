<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

abstract class AbstractAdminHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
    ) {}

    protected function send(BotContext $ctx, int $chatId, string $message, array $extra = []): void
    {
        $this->runtime->sendMessage($ctx, $chatId, $message, $extra);
    }
}
