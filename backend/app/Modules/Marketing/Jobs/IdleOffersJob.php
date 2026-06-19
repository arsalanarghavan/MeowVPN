<?php

namespace App\Modules\Marketing\Jobs;

use App\Modules\Marketing\Services\IdleOffersService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class IdleOffersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(IdleOffersService $idle): void
    {
        CronTimer::run('svp:idle_offers', fn () => $idle->run());
    }
}
