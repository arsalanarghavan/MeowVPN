<?php

namespace App\Modules\Core\Bot\Jobs;

use App\Modules\Core\Bot\Services\BotPollingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BotPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BotPollingService $polling): void
    {
        if (! $polling->anyPlatformNeedsPoll()) {
            return;
        }
        $polling->pollAll();
    }
}
