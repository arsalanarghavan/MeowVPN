<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class PlanMutateFieldsTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('reseller', true);
    }

    public function test_plan_create_persists_owner_wholesale_template_quota(): void
    {
        $res = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'name' => 'Owned Plan',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'owner_svp_user_id' => 42,
            'wholesale_line_id' => 7,
            'panel_template_id' => 3,
            'quota_display_mode' => 'hide_as_unlimited',
            'price' => 1000,
            'active' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $planId = (int) $res->json('plan_id');
        $this->assertGreaterThan(0, $planId);
        $this->assertDatabaseHas('svp_plans', [
            'id' => $planId,
            'name' => 'Owned Plan',
            'owner_svp_user_id' => 42,
            'wholesale_line_id' => 7,
            'panel_template_id' => 3,
            'quota_display_mode' => 'hide_as_unlimited',
        ]);
    }

    public function test_plan_update_clears_zero_wholesale_and_template(): void
    {
        $planId = (int) DB::table('svp_plans')->insertGetId([
            'name' => 'Clearable',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'owner_svp_user_id' => 10,
            'wholesale_line_id' => 5,
            'panel_template_id' => 2,
            'quota_display_mode' => 'hide_as_unlimited',
            'price' => 500,
            'active' => 1,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'update',
            'plan_id' => $planId,
            'name' => 'Clearable',
            'category' => 'normal',
            'panel_id' => 1,
            'wholesale_line_id' => 0,
            'panel_template_id' => 0,
            'quota_display_mode' => 'show',
            'owner_svp_user_id' => 10,
        ])->assertOk()->assertJsonPath('ok', true);

        $row = DB::table('svp_plans')->where('id', $planId)->first();
        $this->assertNull($row->wholesale_line_id);
        $this->assertNull($row->panel_template_id);
        $this->assertSame('show', (string) $row->quota_display_mode);
        $this->assertSame(10, (int) $row->owner_svp_user_id);
    }

    public function test_panel_templates_requires_panel_id(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/panel-templates')
            ->assertStatus(400)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'invalid_panel');
    }

    public function test_panel_templates_xui_panel_returns_empty(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/panel-templates?panel_id=1')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.provider', 'xui')
            ->assertJsonPath('data.templates', []);
    }
}
