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
use Illuminate\Support\Facades\Log;

class PanelServiceSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(XuiClient $xui): void
    {
        CronTimer::run('svp:panel_service_sync', function () use ($xui) {
            $this->sync($xui);
        });
    }

    protected function sync(XuiClient $xui): void
    {
        if (! svp_modules()->isEnabled('xui_panel')) {
            return;
        }
        $services = DB::table('svp_services')->whereNull('deleted_at')->get();
        $groups = [];
        foreach ($services as $svc) {
            if ((string) ($svc->service_type ?? 'xray') === 'l2tp') {
                continue;
            }
            $pid = max(1, (int) ($svc->panel_id ?? 1));
            $iid = (int) ($svc->inbound_id ?? 0);
            if ($iid < 1) {
                continue;
            }
            $key = $pid.'|'.$iid;
            $groups[$key][] = $svc;
        }
        foreach ($groups as $group) {
            if ($group === [] || ! isset($group[0])) {
                continue;
            }
            $first = $group[0];
            $pid = max(1, (int) ($first->panel_id ?? 1));
            $iid = (int) $first->inbound_id;
            $xui->runWithPanel($pid, function () use ($xui, $group, $iid, $pid) {
                if (! $xui->loginWithRetries(4, 250000)) {
                    return;
                }
                $inb = $xui->inboundGet($iid);
                if (! is_array($inb)) {
                    return;
                }
                $settings = $inb['settings'] ?? '';
                $dec = is_string($settings) ? json_decode($settings, true) : (is_array($settings) ? $settings : []);
                $emails = [];
                if (is_array($dec) && ! empty($dec['clients'])) {
                    foreach ($dec['clients'] as $c) {
                        if (is_array($c) && ! empty($c['email'])) {
                            $emails[(string) $c['email']] = true;
                        }
                    }
                }
                if ($emails === []) {
                    return;
                }
                foreach ($group as $svc) {
                    $em = trim((string) ($svc->email ?? ''));
                    if ($em !== '' && ! isset($emails[$em])) {
                        Log::warning('panel_service_sync: email missing on inbound', [
                            'service_id' => (int) $svc->id,
                            'panel_id' => $pid,
                            'inbound_id' => $iid,
                            'email' => $em,
                        ]);
                    }
                }
            });
        }
    }
}
