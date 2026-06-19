<?php

namespace App\Modules\XuiPanel\Services;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InboundMapService
{
    public function __construct(
        protected XuiClient $xui,
        protected SettingsStore $settings,
    ) {}

    public function mapKey(int $panelId): string
    {
        return 'inbound_map_p'.max(1, $panelId);
    }

    /** @return array<int, int> */
    public function getMap(int $panelId): array
    {
        $raw = $this->settings->get($this->mapKey($panelId), []);
        if (! is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $old => $new) {
            $o = (int) $old;
            $n = (int) $new;
            if ($o > 0 && $n > 0) {
                $out[$o] = $n;
            }
        }

        return $out;
    }

    /** @param  array<int|string, mixed>  $map */
    public function saveMap(int $panelId, array $map): void
    {
        $norm = [];
        foreach ($map as $old => $new) {
            $o = (int) $old;
            $n = (int) $new;
            if ($o > 0 && $n > 0) {
                $norm[$o] = $n;
            }
        }
        $this->settings->set($this->mapKey($panelId), $norm);
    }

    /** @return array<string, mixed> */
    public function compareContext(int $panelId): array
    {
        $pid = max(1, $panelId);
        $db = $this->dbInboundsForPanel($pid);
        $live = [];
        $liveRes = $this->xui->runWithPanel($pid, function () {
            if (! $this->xui->loginWithRetries(5, 200000)) {
                return ['ok' => false, 'message' => 'login_fail'];
            }
            $list = $this->xui->inboundsList();

            return ['ok' => true, 'inbounds' => is_array($list) ? $list : []];
        });
        if (! empty($liveRes['ok']) && is_array($liveRes['inbounds'] ?? null)) {
            $live = $liveRes['inbounds'];
        }
        $stored = $this->getMap($pid);
        $suggest = $this->suggestMap($db, $live);
        $liveIds = [];
        foreach ($live as $row) {
            if (is_array($row) && (int) ($row['id'] ?? 0) > 0) {
                $liveIds[(int) $row['id']] = true;
            }
        }
        $missing = [];
        foreach ($db as $idx => $row) {
            $old = (int) ($row['id'] ?? 0);
            if ($old < 1) {
                continue;
            }
            $target = $stored[$old] ?? ($suggest[$old] ?? $old);
            $db[$idx]['on_panel_now'] = isset($liveIds[$target]) || isset($liveIds[$old]);
            if (! $db[$idx]['on_panel_now']) {
                $missing[] = $old;
            }
        }

        return [
            'ok' => ! empty($liveRes['ok']),
            'message' => (string) ($liveRes['message'] ?? ''),
            'panel_id' => $pid,
            'db_inbounds' => $db,
            'panel_inbounds' => $live,
            'map' => $stored,
            'suggested_map' => $suggest,
            'missing_on_panel' => $missing,
        ];
    }

    /** @param  array<int, int>  $map */
    public function applyToDb(int $panelId, array $map): array
    {
        $norm = $this->getMap($panelId);
        foreach ($map as $old => $new) {
            $o = (int) $old;
            $n = (int) $new;
            if ($o > 0 && $n > 0) {
                $norm[$o] = $n;
            }
        }
        $this->saveMap($panelId, $norm);

        $updatedServices = 0;
        $updatedPlans = 0;
        if (Schema::hasTable('svp_services')) {
            foreach ($norm as $old => $new) {
                $updatedServices += DB::table('svp_services')
                    ->where('panel_id', $panelId)
                    ->where('inbound_id', $old)
                    ->whereNull('deleted_at')
                    ->update(['inbound_id' => $new]);
            }
        }
        if (Schema::hasTable('svp_plans')) {
            foreach ($norm as $old => $new) {
                $updatedPlans += DB::table('svp_plans')
                    ->where('panel_id', $panelId)
                    ->where('inbound_id', $old)
                    ->update(['inbound_id' => $new]);
            }
        }

        return [
            'ok' => true,
            'map' => $norm,
            'updated_services' => $updatedServices,
            'updated_plans' => $updatedPlans,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    protected function dbInboundsForPanel(int $panelId): array
    {
        $counts = [];
        if (Schema::hasTable('svp_services')) {
            $rows = DB::table('svp_services')
                ->select('inbound_id', DB::raw('COUNT(*) as service_count'))
                ->where('panel_id', $panelId)
                ->whereNull('deleted_at')
                ->where('inbound_id', '>', 0)
                ->groupBy('inbound_id')
                ->get();
            foreach ($rows as $r) {
                $counts[(int) $r->inbound_id] = (int) $r->service_count;
            }
        }
        $out = [];
        foreach ($counts as $id => $cnt) {
            $out[] = ['id' => $id, 'service_count' => $cnt];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $db
     * @param  array<int, array<string, mixed>>  $live
     * @return array<int, int>
     */
    protected function suggestMap(array $db, array $live): array
    {
        $suggest = [];
        foreach ($db as $row) {
            $old = (int) ($row['id'] ?? 0);
            if ($old < 1) {
                continue;
            }
            foreach ($live as $l) {
                if ((int) ($l['id'] ?? 0) === $old) {
                    $suggest[$old] = $old;
                    break;
                }
            }
        }

        return $suggest;
    }
}
