<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeferredPurchaseCheckoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $chatId,
        public string $platform,
        public int $planId,
        public int $extraGb = 0,
        public int $clientsCount = 1,
    ) {}

    public function handle(BuyHandler $buy): void
    {
        $user = SvpUser::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $ctx = new BotContext($this->platform);
        $buy->sendDeferredCheckout($ctx, $user, $this->chatId, $this->planId, $this->extraGb, $this->clientsCount);
    }
}
