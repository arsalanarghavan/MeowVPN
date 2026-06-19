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

class DeferredC2cInvoiceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $chatId,
        public string $platform,
        public int $txId,
        public int $cardId,
        public ?string $callbackQueryId = null,
    ) {}

    public function handle(BuyHandler $buy): void
    {
        $user = SvpUser::query()->find($this->userId);
        if (! $user) {
            return;
        }

        $ctx = new BotContext($this->platform);
        $buy->deliverCardPayment($ctx, $user, $this->chatId, $this->txId, $this->cardId, $this->callbackQueryId);
    }
}
