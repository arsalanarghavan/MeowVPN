<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\Services\BotConfigDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeferredConfigDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Delivers deferred bot config / subscription messages for Telegram and Bale.
     * Retries with backoff when URIs are not ready yet (WP config_delivery_retry_delays).
     */
    public function __construct(
        public int $userId,
        public int $serviceId,
        public string $platform = 'telegram',
        public string $mode = 'config',
    ) {}

    public function handle(BotConfigDeliveryService $delivery): void
    {
        $delivery->deliver($this->userId, $this->serviceId, '', $this->platform, $this->mode);
    }
}
