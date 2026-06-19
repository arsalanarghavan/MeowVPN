<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use App\Models\SvpReceipt;
use App\Models\SvpUser;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 gaps — A.2.3, B.2.2, C.4.3, E.2.3, F.1.2, F.4.3, F.7.2 */
class GroupAcceptanceGapsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->createSvpTestSchema();
    }

    public function test_reseller_panels_isolation_monitoring(): void
    {
        $resellerId = (int) SvpUser::query()->where('role', 'reseller')->value('id');
        $this->assertGreaterThan(0, $resellerId);

        $dash = DashboardUser::query()->where('username', 'reseller')->first();
        $dash->permissions_json = ['users.manage' => true, 'plans.manage' => true, 'services.manage' => true];
        $dash->save();

        DB::table('svp_reseller_panel_prices')->insert([
            'reseller_svp_user_id' => $resellerId,
            'panel_id' => 1,
            'price' => 1000,
            'active' => 1,
        ]);
        DB::table('svp_panels')->insert([
            'id' => 99,
            'label' => 'Other Panel',
            'panel_url' => 'https://other.test',
            'active' => 1,
            'sort_order' => 99,
            'created_at' => now(),
        ]);

        $this->actingAs($dash);
        $panels = $this->getJson('/api/v1/admin/state?tab=xui_panels')->assertOk()->json('panels');
        $ids = array_map(fn ($p) => (int) ($p['id'] ?? 0), is_array($panels) ? $panels : []);
        $this->assertContains(1, $ids);
        $this->assertNotContains(99, $ids);
    }

    public function test_service_naming_reset_to_defaults(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('service_naming.service_naming_mode', 'custom');
        $settings->set('service_naming.service_naming_prefix', 'X');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'service_naming',
            'service_naming_mode' => 'legacy',
            'service_naming_prefix' => '',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('legacy', (string) $settings->get('service_naming.service_naming_mode'));
        $this->assertSame('', (string) $settings->get('service_naming.service_naming_prefix'));
    }

    public function test_user_merge_writes_audit_log(): void
    {
        SvpUser::query()->create(['username' => 'merge_audit_a', 'role' => 'user', 'status' => 'approved']);
        SvpUser::query()->create(['username' => 'merge_audit_b', 'role' => 'user', 'status' => 'approved']);
        $keep = (int) SvpUser::query()->where('username', 'merge_audit_a')->value('id');
        $drop = (int) SvpUser::query()->where('username', 'merge_audit_b')->value('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge',
            'keep_id' => $keep,
            'drop_id' => $drop,
            'confirm' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_audit_log', [
            'event_type' => 'user_merge',
            'domain' => 'admin',
        ]);
    }

    public function test_configs_assign_plan_mutate(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');
        $planId = (int) DB::table('svp_plans')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_assign_plan',
            'service_id' => $svcId,
            'plan_id' => $planId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame($planId, (int) DB::table('svp_services')->where('id', $svcId)->value('plan_id'));
    }

    public function test_reseller_panel_price_below_floor_rejected(): void
    {
        $parentId = (int) SvpUser::query()->where('role', 'reseller')->value('id');
        $child = SvpUser::query()->create([
            'username' => 'child_reseller',
            'role' => 'reseller',
            'status' => 'approved',
            'invited_by' => $parentId,
            'created_at' => now(),
        ]);

        DB::table('svp_reseller_parent_panel_floors')->insert([
            'parent_svp_user_id' => $parentId,
            'child_svp_user_id' => $child->id,
            'panel_id' => 1,
            'min_price_per_gb' => 500,
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_panel_prices_save',
            'reseller_svp_user_id' => $child->id,
            'parent_svp_user_id' => $parentId,
            'prices' => [['panel_id' => 1, 'price' => 100]],
        ])->assertOk()->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'price_below_floor');
    }

    public function test_reseller_receipts_scoped_to_downline(): void
    {
        $resellerId = (int) SvpUser::query()->where('role', 'reseller')->value('id');
        $outsider = SvpUser::query()->create([
            'username' => 'outsider_rcpt',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        SvpReceipt::query()->create([
            'user_id' => $outsider->id,
            'amount' => 1000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $dash = DashboardUser::query()->where('username', 'reseller')->first();
        $dash->permissions_json = ['users.manage' => true, 'plans.manage' => true, 'receipts.review' => true];
        $dash->save();
        $this->actingAs($dash);

        $rows = $this->getJson('/api/v1/admin/state?tab=receipts')->assertOk()->json('receipts');
        $userIds = array_map(fn ($r) => (int) ($r['user_id'] ?? 0), is_array($rows) ? $rows : []);
        $this->assertNotContains($outsider->id, $userIds);
    }

    public function test_reseller_wallet_topup_checkout(): void
    {
        $reseller = DashboardUser::query()->where('username', 'reseller')->first();
        $this->actingAs($reseller);

        $res = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_wallet_topup_checkout',
            'amount' => 50000,
        ])->assertOk()->assertJsonPath('ok', true);

        $txId = (int) $res->json('data.transaction_id');
        $this->assertGreaterThan(0, $txId);
        $this->assertDatabaseHas('svp_transactions', [
            'id' => $txId,
            'type' => 'reseller_wallet_topup',
            'status' => 'pending',
        ]);
    }
}
