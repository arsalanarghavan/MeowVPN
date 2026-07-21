<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class PlanResellerRulesTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('reseller', true);
    }

    public function test_reseller_cannot_mutate_foreign_plan(): void
    {
        $planId = (int) DB::table('svp_plans')->insertGetId([
            'name' => 'Foreign',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'owner_svp_user_id' => 999,
            'price' => 1000,
            'active' => 1,
            'created_at' => now(),
        ]);

        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'toggle',
            'plan_id' => $planId,
        ])->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'forbidden_scope');
    }

    public function test_reseller_create_requires_wholesale_line_when_multiple_assigned(): void
    {
        DB::table('svp_reseller_panel_prices')->updateOrInsert(
            ['reseller_svp_user_id' => 100, 'panel_id' => 1],
            [
                'price_per_gb' => 100,
                'panel_access' => 1,
                'default_service_type' => 'xray',
                'default_inbound_id' => 1,
                'updated_at' => now(),
            ]
        );

        $lineA = (int) DB::table('svp_reseller_wholesale_lines')->insertGetId([
            'panel_id' => 1,
            'inbound_id' => 1,
            'label' => 'A',
            'price_per_gb' => 500,
            'active' => 1,
            'created_at' => now(),
        ]);
        $lineB = (int) DB::table('svp_reseller_wholesale_lines')->insertGetId([
            'panel_id' => 1,
            'inbound_id' => 1,
            'label' => 'B',
            'price_per_gb' => 600,
            'active' => 1,
            'created_at' => now(),
        ]);
        foreach ([$lineA, $lineB] as $lid) {
            DB::table('svp_reseller_wholesale_line_assignments')->insert([
                'reseller_svp_user_id' => 100,
                'line_id' => $lid,
                'created_at' => now(),
            ]);
        }

        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'add',
            'name' => 'Needs Line',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'price' => 5000,
            'traffic_gb' => 10,
            'pricing_type' => 'fixed',
            'active' => 1,
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'wholesale_line_required')
            ->assertJsonPath('code', 'wholesale_line_required');
    }

    public function test_reseller_create_with_line_forces_owner(): void
    {
        DB::table('svp_reseller_panel_prices')->updateOrInsert(
            ['reseller_svp_user_id' => 100, 'panel_id' => 1],
            [
                'price_per_gb' => 100,
                'panel_access' => 1,
                'default_service_type' => 'xray',
                'default_inbound_id' => 1,
                'updated_at' => now(),
            ]
        );

        $lineId = (int) DB::table('svp_reseller_wholesale_lines')->insertGetId([
            'panel_id' => 1,
            'inbound_id' => 5,
            'label' => 'Solo',
            'price_per_gb' => 200,
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('svp_reseller_wholesale_line_assignments')->insert([
            'reseller_svp_user_id' => 100,
            'line_id' => $lineId,
            'created_at' => now(),
        ]);

        $res = $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'plan_action' => 'add',
            'name' => 'Owned',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'wholesale_line_id' => $lineId,
            'price' => 5000,
            'traffic_gb' => 10,
            'pricing_type' => 'fixed',
            'active' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $planId = (int) $res->json('plan_id');
        $this->assertDatabaseHas('svp_plans', [
            'id' => $planId,
            'owner_svp_user_id' => 100,
            'wholesale_line_id' => $lineId,
        ]);
    }

    public function test_plans_state_filters_by_owner_for_reseller(): void
    {
        DB::table('svp_plans')->insert([
            [
                'name' => 'Site',
                'category' => 'normal',
                'panel_id' => 1,
                'inbound_id' => 1,
                'owner_svp_user_id' => 0,
                'price' => 100,
                'active' => 1,
                'created_at' => now(),
            ],
            [
                'name' => 'Mine',
                'category' => 'normal',
                'panel_id' => 1,
                'inbound_id' => 1,
                'owner_svp_user_id' => 100,
                'price' => 200,
                'active' => 1,
                'created_at' => now(),
            ],
            [
                'name' => 'OtherReseller',
                'category' => 'normal',
                'panel_id' => 1,
                'inbound_id' => 1,
                'owner_svp_user_id' => 999,
                'price' => 300,
                'active' => 1,
                'created_at' => now(),
            ],
        ]);

        $json = $this->actingAsReseller()->getJson('/api/v1/admin/state?activeTab=plans')->assertOk()->json();
        $plans = $json['plans'] ?? [];
        $this->assertNotEmpty($plans);
        foreach ($plans as $plan) {
            $this->assertSame(100, (int) ($plan['owner_svp_user_id'] ?? -1));
        }
        $names = array_column($plans, 'name');
        $this->assertContains('Mine', $names);
        $this->assertNotContains('Site', $names);
        $this->assertNotContains('OtherReseller', $names);
    }

    public function test_admin_reseller_context_filters_plans_by_owner(): void
    {
        DB::table('svp_plans')->insert([
            [
                'name' => 'SiteAdmin',
                'category' => 'normal',
                'panel_id' => 1,
                'inbound_id' => 1,
                'owner_svp_user_id' => 0,
                'price' => 100,
                'active' => 1,
                'created_at' => now(),
            ],
            [
                'name' => 'CtxReseller',
                'category' => 'normal',
                'panel_id' => 1,
                'inbound_id' => 1,
                'owner_svp_user_id' => 100,
                'price' => 200,
                'active' => 1,
                'created_at' => now(),
            ],
        ]);

        $json = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?activeTab=plans&resellerContextId=100')
            ->assertOk()
            ->json();
        $plans = $json['plans'] ?? [];
        foreach ($plans as $plan) {
            $this->assertSame(100, (int) ($plan['owner_svp_user_id'] ?? -1));
        }
        $names = array_column($plans, 'name');
        $this->assertContains('CtxReseller', $names);
        $this->assertNotContains('SiteAdmin', $names);
    }

    public function test_merge_reseller_plan_defaults_uses_catalog_when_panel_row_disallows(): void
    {
        DB::table('svp_reseller_panel_prices')->updateOrInsert(
            ['reseller_svp_user_id' => 100, 'panel_id' => 1],
            [
                'price_per_gb' => 0,
                'panel_access' => 0,
                'default_service_type' => 'xray',
                'default_inbound_id' => 99,
                'updated_at' => now(),
            ]
        );
        DB::table('svp_reseller_wholesale_line_assignments')->where('reseller_svp_user_id', 100)->delete();
        $lineId = (int) DB::table('svp_reseller_wholesale_lines')->insertGetId([
            'panel_id' => 1,
            'inbound_id' => 7,
            'label' => 'Catalog L2TP',
            'default_service_type' => 'l2tp',
            'default_inbound_id' => 0,
            'default_l2tp_server_id' => 3,
            'price_per_gb' => 200,
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('svp_reseller_wholesale_line_assignments')->insert([
            'reseller_svp_user_id' => 100,
            'line_id' => $lineId,
            'created_at' => now(),
        ]);

        $svc = app(\App\Modules\Commerce\Services\PlanResellerRulesService::class);
        $merged = $svc->mergeResellerPlanDefaults(100, ['panel_id' => 1, 'service_type' => 'xray', 'inbound_id' => 1]);

        $this->assertSame('l2tp', (string) ($merged['service_type'] ?? ''));
        $this->assertSame(0, (int) ($merged['inbound_id'] ?? -1));
        $this->assertSame(3, (int) ($merged['l2tp_server_id'] ?? 0));
        if (array_key_exists('inbound_ids', $merged)) {
            $this->assertNull($merged['inbound_ids']);
        }
    }
}
