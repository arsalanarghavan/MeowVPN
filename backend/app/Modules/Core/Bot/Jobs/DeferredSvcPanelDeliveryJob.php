<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\Services\BotSvcPanelDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * WP schedule_svc_panel_full_delivery / svp_deferred_svc_panel_delivery.
 * Refreshes or edits the live service panel message after a fast callback ack.
 */
class DeferredSvcPanelDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $platform,
        public int $chatId,
        public int $panelMsgId,
        public int $serviceId,
        public int $ownerUserId,
        public int $actingUserId,
        public string $action = 'p',
    ) {}

    public function handle(BotSvcPanelDeliveryService $delivery): void
    {
        $delivery->run(
            $this->platform,
            $this->chatId,
            $this->panelMsgId,
            $this->serviceId,
            $this->ownerUserId,
            $this->actingUserId,
            $this->action,
        );
    }
}
