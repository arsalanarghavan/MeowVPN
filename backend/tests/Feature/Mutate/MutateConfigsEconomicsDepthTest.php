<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateConfigsEconomicsDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_shared_economics_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'shared_economics_save',
            'shared_cost_per_gb' => 1200,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_configs_client_delete(): void
    {
        $clientId = (int) DB::table('svp_panel_inbound_clients')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_delete',
            'client_id' => $clientId,
        ])->assertOk();
    }

    public function test_configs_delete_expired_linked(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_delete_expired_linked',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_panel_test_returns_connection_result(): void
    {
        \Illuminate\Support\Facades\Http::fake(['*' => \Illuminate\Support\Facades\Http::response(['success' => true], 200)]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_test',
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_panel_economics_save_and_mark_paid(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_economics_save',
            'panel_id' => 1,
            'monthly_cost' => 200000,
        ])->assertOk()->assertJsonPath('ok', true);

        DB::table('svp_panel_economics_lines')->insert([
            'panel_id' => 1,
            'label' => 'host',
            'amount' => 50,
            'active' => 1,
            'expires_at' => now()->addDays(2)->toDateString(),
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_economics_mark_paid',
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_unit_economics_save_and_config_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'unit_economics_config_save',
            'margin_pct' => 25,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'unit_economics_save',
            'panel_id' => 1,
            'cost_per_gb' => 900,
        ])->assertOk();
    }

    public function test_purge_expired_ops(): void
    {
        app(\App\Services\SettingsStore::class)->set('purge_expired_enabled', false);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_run_cron',
            'force' => true,
        ])->assertOk()->assertJsonStructure(['data']);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_purge_ready',
            'confirm' => true,
            'limit' => 5,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_purge_one',
            'service_id' => 99999,
        ])->assertOk()->assertJsonPath('ok', false);
    }
}
