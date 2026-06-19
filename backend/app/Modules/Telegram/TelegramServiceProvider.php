<?php

namespace App\Modules\Telegram;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Telegram\Mutations\BotMutations;

class TelegramServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'telegram';
    }

    public function mutationHandlers(): array
    {
        return app(BotMutations::class)->handlersForPlatform('telegram');
    }

    protected function bootEnabled(): void
    {
        //
    }
}
