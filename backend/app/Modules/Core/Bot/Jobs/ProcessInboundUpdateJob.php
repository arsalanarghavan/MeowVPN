<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\UpdateRouter;
use App\Modules\Reseller\Services\ResellerBotProfileService;
use App\Modules\Telegram\Services\TelegramMirrorBotService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessInboundUpdateJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** @param  array<string, mixed>  $update */
    public function __construct(
        public string $platform,
        public array $update,
        public int $resellerSvpUserId = 0,
        public int $mirrorBotId = 0,
    ) {}

    public function handle(
        UpdateRouter $router,
        ResellerBotProfileService $profiles,
        TelegramMirrorBotService $mirrors,
    ): void {
        if (isset($this->update['_drain'])) {
            app(\App\Services\Bot\InboundQueueService::class)->drainBatch();

            return;
        }

        $mirrorProfile = null;
        $resellerProfile = null;
        $resellerId = $this->resellerSvpUserId;
        $mirrorId = $this->mirrorBotId;

        if ($mirrorId > 0) {
            $mirrorProfile = $mirrors->profileArrayForRuntime($mirrorId);
            $resellerId = 0;
        } elseif ($resellerId > 0) {
            $resellerProfile = $profiles->profileArrayForRuntime($resellerId);
        }

        $ctx = new BotContext(
            platform: $this->platform === 'bale' ? 'bale' : 'telegram',
            resellerSvpUserId: $resellerId,
            resellerProfile: $resellerProfile,
            mirrorBotId: $mirrorId,
            mirrorProfile: $mirrorProfile,
        );

        try {
            $router->dispatch($ctx, $this->update);
        } finally {
            $ctx->reset();
        }
    }
}
