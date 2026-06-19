<?php

namespace App\Modules\XuiPanel\Jobs;

use App\Modules\XuiPanel\Services\XuiClient;
use App\Support\Metrics\CronTimer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelOnlineJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(XuiClient $xui): void
    {
        CronTimer::run('svp:panel_online', function () use ($xui) {
            $this->sync($xui);
        });
    }

    protected function sync(XuiClient $xui): void
    {
        if (! svp_modules()->isEnabled('xui_panel') || ! Schema::hasTable('svp_panel_online_daily')) {
            return;
        }
        $statDate = now()->toDateString();
        $panels = DB::table('svp_panels')->where('active', 1)->orderBy('sort_order')->get();
        foreach ($panels as $pn) {
            $pid = (int) $pn->id;
            if ($pid < 1) {
                continue;
            }
            $n = (int) $xui->runWithPanel($pid, function () use ($xui) {
                if (! $xui->loginWithRetries(6, 300000)) {
                    return 0;
                }
                $j = $xui->onlines();

                return $xui->countOnlinesResponse($j);
            });
            $existing = DB::table('svp_panel_online_daily')
                ->where('panel_id', $pid)
                ->where('stat_date', $statDate)
                ->first();
            if ($existing) {
                $max = max((int) $existing->max_online, $n);
                DB::table('svp_panel_online_daily')
                    ->where('id', $existing->id)
                    ->update(['max_online' => $max, 'updated_at' => now()]);
            } else {
                DB::table('svp_panel_online_daily')->insert([
                    'panel_id' => $pid,
                    'stat_date' => $statDate,
                    'max_online' => $n,
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
