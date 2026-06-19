<?php

namespace App\Services\AdminState;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PanelHealthService
{
    /**
     * @param  array<string, mixed>  $overview
     * @param  array<int, array<string, mixed>>  $panels
     * @return array<string, mixed>
     */
    public function enrichOverview(array $overview, array $panels, AdminStateContext $ctx): array
    {
        $overview['live'] = $overview['live'] ?? ['panels' => []];
        $livePanels = [];

        foreach ($panels as $panel) {
            $pid = (int) ($panel['id'] ?? 0);
            if ($pid < 1) {
                continue;
            }
            $livePanels[] = [
                'panel_id' => $pid,
                'label' => (string) ($panel['label'] ?? ''),
                'health' => $this->panelHealthBadge($pid),
                'max_online_today' => $this->maxOnlineToday($pid),
            ];
        }

        $overview['live']['panels'] = $livePanels;

        return $overview;
    }

    protected function panelHealthBadge(int $panelId): string
    {
        if (! Schema::hasTable('svp_panel_online_daily')) {
            return 'unknown';
        }

        $row = DB::table('svp_panel_online_daily')
            ->where('panel_id', $panelId)
            ->where('stat_date', now()->toDateString())
            ->first();

        if (! $row) {
            return 'unknown';
        }

        return ((int) ($row->max_online ?? 0)) > 0 ? 'ok' : 'idle';
    }

    protected function maxOnlineToday(int $panelId): int
    {
        if (! Schema::hasTable('svp_panel_online_daily')) {
            return 0;
        }

        $row = DB::table('svp_panel_online_daily')
            ->where('panel_id', $panelId)
            ->where('stat_date', now()->toDateString())
            ->first();

        return (int) ($row->max_online ?? 0);
    }
}
