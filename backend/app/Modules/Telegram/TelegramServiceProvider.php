<?php

namespace App\Modules\Telegram;

use App\Modules\AbstractModuleServiceProvider;
use App\Modules\Telegram\Mutations\BotMutations;
use App\Modules\Telegram\Mutations\TelegramMirrorMutations;

class TelegramServiceProvider extends AbstractModuleServiceProvider
{
    public function moduleKey(): string
    {
        return 'telegram';
    }

    public function mutationHandlers(): array
    {
        return array_merge(
            app(BotMutations::class)->handlersForPlatform('telegram'),
            app(TelegramMirrorMutations::class)->handlers(),
        );
    }

    protected function bootEnabled(): void
    {
        //
    }
}
