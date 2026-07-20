<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutatePanelMergeTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        DB::table('svp_panels')->updateOrInsert(
            ['id' => 2],
            [
                'label' => 'Panel 2',
                'panel_url' => 'https://panel2.test',
                'panel_username' => 'admin',
                'panel_password' => 'secret',
                'panel_api_base' => 'panel/api',
                'panel_api_flavor' => 'legacy_inbound',
                'sort_order' => 2,
                'active' => 1,
                'created_at' => now(),
            ]
        );
    }

    public function test_panel_merge_preview_bad_params(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_merge_preview',
            'source_panel_id' => 0,
            'target_panel_id' => 2,
        ])->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_panel_merge_preview_returns_mapping(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_merge_preview',
            'source_panel_id' => 1,
            'target_panel_id' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['data' => ['merge_mode', 'source_plans', 'target_plans', 'total_services']]);
    }

    public function test_panel_merge_execute_requires_services(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_merge_execute',
            'source_panel_id' => 1,
            'target_panel_id' => 2,
            'plan_map' => ['1' => 1],
        ])->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_plan_update_triggers_inbound_sync_skip_when_unchanged(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'update',
            'plan_id' => 1,
            'name' => 'Plan 1 updated',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'active' => 1,
        ])
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('inbound_sync.mode', 'skipped');
    }
}
