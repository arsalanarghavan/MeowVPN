<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UnitEconomicsService
{
    public function __construct(protected SettingsStore $settings) {}

    /** @param  array<string, mixed>  $payload */
    public function savePanelEconomics(array $payload): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return svp_err('invalid');
        }

        $lineAction = (string) ($payload['line_action'] ?? '');
        if ($lineAction !== '' && Schema::hasTable('svp_panel_economics_lines')) {
            return $this->mutatePanelLine($panelId, $lineAction, $payload);
        }

        $this->settings->set("panel_economics.{$panelId}", collect($payload)->except(['op', 'panel_id', 'line_action', 'line_id'])->all());

        return svp_ok(['panel_id' => $panelId]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function mutatePanelLine(int $panelId, string $action, array $payload): array
    {
        $lineId = (int) ($payload['line_id'] ?? 0);

        if ($action === 'delete' && $lineId > 0) {
            DB::table('svp_panel_economics_lines')->where('id', $lineId)->where('panel_id', $panelId)->delete();

            return svp_ok(['panel_id' => $panelId, 'line_id' => $lineId]);
        }

        if ($action === 'deactivate' && $lineId > 0) {
            DB::table('svp_panel_economics_lines')->where('id', $lineId)->where('panel_id', $panelId)->update(['active' => 0]);

            return svp_ok(['panel_id' => $panelId, 'line_id' => $lineId]);
        }

        if ($action === 'edit' && $lineId > 0) {
            DB::table('svp_panel_economics_lines')->where('id', $lineId)->where('panel_id', $panelId)->update([
                'label' => (string) ($payload['label'] ?? ''),
                'amount' => (float) ($payload['amount'] ?? 0),
                'active' => (int) ($payload['active'] ?? 1),
            ]);

            return svp_ok(['panel_id' => $panelId, 'line_id' => $lineId]);
        }

        if ($action === 'add') {
            $id = (int) DB::table('svp_panel_economics_lines')->insertGetId([
                'panel_id' => $panelId,
                'label' => (string) ($payload['label'] ?? ''),
                'amount' => (float) ($payload['amount'] ?? 0),
                'active' => (int) ($payload['active'] ?? 1),
                'created_at' => now(),
            ]);

            return svp_ok(['panel_id' => $panelId, 'line_id' => $id]);
        }

        return svp_err('invalid_line_action');
    }

    /** @param  array<string, mixed>  $payload */
    public function markPanelPaid(array $payload): array
    {
        $panelId = (int) ($payload['panel_id'] ?? 0);
        if ($panelId < 1) {
            return svp_err('invalid');
        }

        DB::table('svp_panel_economics')->updateOrInsert(
            ['panel_id' => $panelId],
            ['paid_at' => now(), 'updated_at' => now()]
        );

        return svp_ok(['panel_id' => $panelId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function saveSharedEconomics(array $payload): array
    {
        $this->settings->merge(collect($payload)->except(['op'])->all());

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function saveUnitEconomics(array $payload): array
    {
        $this->settings->set('unit_economics', collect($payload)->except(['op'])->all());

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function saveUnitEconomicsConfig(array $payload): array
    {
        $this->settings->set('unit_economics_config', collect($payload)->except(['op'])->all());

        return svp_ok();
    }
}
