<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\UpdateRouter;
use App\Modules\Reseller\Services\ResellerBotProfileService;
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
    ) {}

    public function handle(UpdateRouter $router, ResellerBotProfileService $profiles): void
    {
        if (isset($this->update['_drain'])) {
            app(\App\Services\Bot\InboundQueueService::class)->drainBatch();

            return;
        }

        $profile = null;
        if ($this->resellerSvpUserId > 0) {
            $profile = $profiles->profileArrayForRuntime($this->resellerSvpUserId);
        }

        $ctx = new BotContext(
            platform: $this->platform === 'bale' ? 'bale' : 'telegram',
            resellerSvpUserId: $this->resellerSvpUserId,
            resellerProfile: $profile,
        );

        try {
            $router->dispatch($ctx, $this->update);
        } finally {
            $ctx->reset();
        }
    }
}
