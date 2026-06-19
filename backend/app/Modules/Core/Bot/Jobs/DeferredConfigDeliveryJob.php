<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\Services\BotConfigDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class DeferredConfigDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public int $serviceId,
    ) {}

    public function handle(BotConfigDeliveryService $delivery): void
    {
        $payload = Cache::get("bot_config_delivery:{$this->userId}:{$this->serviceId}");
        $cbId = is_array($payload) ? (string) ($payload['cb_id'] ?? '') : '';
        $delivery->deliver($this->userId, $this->serviceId, $cbId);
    }
}
