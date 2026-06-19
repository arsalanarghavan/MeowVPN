<?php

namespace Tests\Feature\Xui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ConfigsSnapshotTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        DB::table('svp_plans')->where('id', 1)->update(['inbound_id' => 1]);
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'cached@local',
            'enable' => 1,
            'synced_at' => now(),
        ]);
    }

    public function test_configs_snapshot_returns_plans_shape(): void
    {
        $this->actingAsAdmin();
        $response = $this->getJson('/api/v1/admin/configs-snapshot?panel_id=1');
        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonStructure(['data' => ['panel_id', 'plans', 'cache_stale']]);
    }
}
