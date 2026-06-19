<?php

namespace App\Modules\Core\Bot\Services;

use Illuminate\Support\Facades\Cache;

class BotDeferredCacheService
{
    public function putCheckout(
        int $userId,
        int $chatId,
        string $platform,
        int $planId,
        int $extraGb = 0,
        int $clientsCount = 1,
    ): void {
        Cache::put("bot_deferred_checkout:{$userId}", [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'platform' => $platform,
            'plan_id' => $planId,
            'extra_gb' => $extraGb,
            'clients_count' => $clientsCount,
        ], 600);
    }

    public function putC2c(
        int $userId,
        int $chatId,
        string $platform,
        int $transactionId,
        int $cardId,
        ?string $cbId = null,
    ): void {
        Cache::put("bot_deferred_c2c:{$userId}:{$transactionId}", [
            'user_id' => $userId,
            'chat_id' => $chatId,
            'platform' => $platform,
            'transaction_id' => $transactionId,
            'card_id' => $cardId,
            'cb_id' => $cbId,
        ], 600);
    }
}
