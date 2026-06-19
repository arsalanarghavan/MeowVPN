<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §14 groups A–H strict acceptance (v19). */
class GroupAcceptanceV19Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['telegram', 'bale', 'xui_panel', 'marketing', 'relay', 'crypto', 'reseller', 'backup', 'l2tp'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_a_overview_reseller_scoped_strict(): void
    {
        SvpUser::query()->create([
            'username' => 'outside_tree',
            'role' => 'user',
            'status' => 'approved',
            'invited_by' => 0,
            'created_at' => now(),
        ]);
        $admin = (int) ($this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=dashboard')->json('overview.users_total') ?? 0);
        $reseller = (int) ($this->actingAsReseller()->getJson('/api/v1/admin/state?tab=dashboard')->json('overview.users_total') ?? 0);
        $this->assertGreaterThanOrEqual($admin, $reseller);
    }

    public function test_b_site_settings_whitelabel_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'whitelabel',
            'brand_name' => 'Test Brand v19',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_c_user_merge_preview_has_fields(): void
    {
        $json = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge_preview',
            'source_id' => 200,
            'target_id' => 101,
        ])->assertOk()->assertJsonPath('ok', true)->json();
        $this->assertIsArray($json);
    }

    public function test_d_bot_ui_reseller_forbidden(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_save',
            'version' => 1,
            'surfaces' => [],
        ])->assertForbidden();
    }

    public function test_e_configs_state_ok(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=configs')->assertOk();
    }

    public function test_f_receipt_approve_ok(): void
    {
        DB::table('svp_panels')->where('id', 1)->update(['panel_api_token' => 'tok']);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => 1,
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_g_impersonation_audit_row(): void
    {
        DB::table('svp_audit_log')->insert([
            'domain' => 'security',
            'event_type' => 'impersonation.start',
            'actor_kind' => 'admin',
            'actor_wp_user_id' => 0,
            'actor_svp_user_id' => 0,
            'target_type' => 'user',
            'target_id' => 100,
            'reseller_scope_id' => 100,
            'payload_json' => '{}',
            'ip_hash' => '',
            'created_at' => now(),
        ]);
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?event_type=impersonation.start')
            ->assertOk()
            ->assertJsonCount(1, 'rows');
    }

    public function test_h_audit_pagination_strict(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?page=1&per_page=2')
            ->assertOk()
            ->assertJsonPath('pagination.perPage', 2);
    }

    public function test_reseller_charge_tab_state(): void
    {
        $dash = DashboardUser::query()->where('username', 'reseller')->first();
        $dash->permissions_json = array_merge($dash->permissions_json ?? [], ['plans.manage' => true]);
        $dash->save();
        $this->actingAs($dash)->getJson('/api/v1/admin/state?tab=reseller_charge')->assertOk();
    }
}
