<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\PanelSessionKeeperService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PanelSessionKeeperJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(PanelSessionKeeperService $keeper): void
    {
        CronTimer::run('svp:panel_session_keeper', fn () => $keeper->run());
    }
}
