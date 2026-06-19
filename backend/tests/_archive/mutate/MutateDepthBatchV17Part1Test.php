<?php

namespace Tests\Feature\Mutate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth smoke — batch 1 ops without dedicated depth file (v17). */
class MutateDepthBatchV17Part1Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('xui_panel', true);
        $this->setModuleEnabled('l2tp', true);
        $this->setModuleEnabled('telegram', true);
    }

    public function test_logs_clear_mutate(): void
    {
        DB::table('svp_logs')->insert([
            'level' => 'info',
            'message' => 'test',
            'context_json' => '{}',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', ['op' => 'logs_clear'])
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_shared_economics_save_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'shared_economics_save',
            'panel_id' => 1,
            'monthly_cost' => 500000,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_l2tp_delete_mutate(): void
    {
        $id = (int) DB::table('svp_l2tp_servers')->insertGetId([
            'label' => 'tmp',
            'ssh_host' => '10.0.0.2',
            'l2tp_host' => 'l2tp.tmp',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_delete',
            'id' => $id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_l2tp_servers', ['id' => $id]);
    }

    public function test_purge_expired_purge_ready_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_purge_ready',
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
