<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class InboundClientsCacheJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ConfigsSyncService $configs): void
    {
        CronTimer::run('svp:inbound_clients_cache', function () use ($configs) {
            $this->cache($configs);
        });
    }

    protected function cache(ConfigsSyncService $configs): void
    {
        if (! svp_modules()->isEnabled('xui_panel')) {
            return;
        }
        $panels = DB::table('svp_panels')->where('active', 1)->orderBy('sort_order')->get();
        foreach ($panels as $pn) {
            $pid = (int) $pn->id;
            if ($pid < 1) {
                continue;
            }
            $configs->syncPanelToDb($pid, false);
        }
    }
}
