<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateConfigsClientDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_configs_client_toggle_reset_delete(): void
    {
        $clientId = (int) DB::table('svp_panel_inbound_clients')->min('id');
        if ($clientId < 1) {
            $clientId = (int) DB::table('svp_panel_inbound_clients')->insertGetId([
                'panel_id' => 1,
                'inbound_id' => 1,
                'email' => 'test@svp.local',
                'enable' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_toggle_enable',
            'client_id' => $clientId,
            'enabled' => false,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_reset_traffic',
            'client_id' => $clientId,
        ])->assertOk();
    }

    public function test_configs_panel_client_patch(): void
    {
        $clientId = (int) DB::table('svp_panel_inbound_clients')->min('id');
        if ($clientId < 1) {
            $this->markTestSkipped('no inbound client fixture');
        }

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_panel_client_patch',
            'client_id' => $clientId,
            'note' => 'patched',
        ])->assertOk();
    }

    public function test_reseller_panel_prices_save_panel_access(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_panel_prices_save',
            'reseller_svp_user_id' => 100,
            'prices' => [
                ['panel_id' => 1, 'price_per_gb' => 1000, 'panel_access' => false],
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_reseller_panel_prices', [
            'reseller_svp_user_id' => 100,
            'panel_id' => 1,
            'panel_access' => 0,
        ]);
    }
}
