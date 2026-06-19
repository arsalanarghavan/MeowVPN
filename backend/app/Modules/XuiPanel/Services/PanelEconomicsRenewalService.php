<?php

namespace App\Modules\XuiPanel\Services;

use App\Modules\Core\Services\AdminNotifyService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelEconomicsRenewalService
{
    public function __construct(
        protected SettingsStore $settings,
        protected AdminNotifyService $adminNotify,
    ) {}

    public function run(): void
    {
        if (! $this->settings->get('enabled', true) || ! $this->settings->get('notify_panel_cost_expiry', true)) {
            return;
        }
        if (! Schema::hasTable('svp_panel_economics_lines')) {
            return;
        }

        $offsets = $this->reminderOffsets();
        $rows = DB::table('svp_panel_economics_lines as l')
            ->leftJoin('svp_panels as pn', 'pn.id', '=', 'l.panel_id')
            ->where('l.active', 1)
            ->whereNotNull('l.expires_at')
            ->whereRaw('DATEDIFF(l.expires_at, UTC_DATE()) IN ('.implode(',', $offsets).')')
            ->orderBy('l.expires_at')
            ->limit(200)
            ->get(['l.*', 'pn.label as panel_label']);

        foreach ($rows as $row) {
            $lineId = (int) $row->id;
            $expires = (string) ($row->expires_at ?? '');
            if ($lineId < 1 || $expires === '') {
                continue;
            }
            $daysLeft = (int) floor((strtotime($expires.' 00:00:00 UTC') - strtotime(gmdate('Y-m-d').' 00:00:00 UTC')) / 86400);
            if (! in_array($daysLeft, $offsets, true)) {
                continue;
            }
            $key = 'svp_panel_econ_exp_'.$lineId.'_'.$daysLeft;
            if (Cache::has($key)) {
                continue;
            }
            $label = (string) ($row->panel_label ?? 'Panel #'.(int) ($row->panel_id ?? 0));
            $this->adminNotify->notifyAdmins(
                '💰 هزینه زیرساخت «'.$label.'» تا '.$daysLeft.' روز دیگر منقضی می‌شود.'
            );
            Cache::put($key, 1, now()->addDays(3));
        }
    }

    /** @return list<int> */
    protected function reminderOffsets(): array
    {
        $raw = $this->settings->get('panel_cost_reminder_days', [7, 1, 0]);
        if (! is_array($raw)) {
            return [7, 1, 0];
        }
        $out = array_values(array_unique(array_map('intval', $raw)));

        return $out !== [] ? $out : [7, 1, 0];
    }
}
