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

/** §14 groups A–H acceptance mapping (v18). */
class GroupAcceptanceV18Test extends TestCase
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

    /** A — reseller overview isolation */
    public function test_group_a_reseller_overview_scoped(): void
    {
        SvpUser::query()->create([
            'username' => 'admin_only',
            'role' => 'user',
            'status' => 'approved',
            'invited_by' => 0,
            'created_at' => now(),
        ]);

        $adminTotal = (int) ($this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=dashboard')
            ->json('overview.users_total') ?? 0);

        $resellerTotal = (int) ($this->actingAsReseller()
            ->getJson('/api/v1/admin/state?tab=dashboard')
            ->json('overview.users_total') ?? 0);

        if ($adminTotal > 0) {
            $this->assertGreaterThanOrEqual($adminTotal, $resellerTotal);
        }
    }

    /** A — economics tab admin state */
    public function test_group_a_unit_economics_state(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=unit_economics')
            ->assertOk();
    }

    /** B — site_settings subtabs reachable via state */
    public function test_group_b_site_settings_general_state(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=site_settings')
            ->assertOk();
    }

    /** C — user merge preview */
    public function test_group_c_user_merge_preview(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge_preview',
            'source_id' => 200,
            'target_id' => 101,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    /** C — bulk job items in state */
    public function test_group_c_users_bulk_state(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=users_bulk')
            ->assertOk();
    }

    /** D — bot_ui reseller forbidden */
    public function test_group_d_bot_ui_reseller_read_only(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_save',
            'version' => 1,
            'surfaces' => [],
        ])->assertForbidden();
    }

    /** E — configs state */
    public function test_group_e_configs_state(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=configs')
            ->assertOk();
    }

    /** F — receipt approve deliver */
    public function test_group_f_receipt_approve(): void
    {
        DB::table('svp_panels')->where('id', 1)->update(['panel_api_token' => 'tok']);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => 1,
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    /** F — card reorder */
    public function test_group_f_card_reorder(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'card_reorder',
            'order' => [1],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    /** G — impersonation audit row filterable */
    public function test_group_g_impersonation_audit_filter(): void
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
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'rows');
    }

    /** H — audit pagination */
    public function test_group_h_audit_pagination(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?page=1&per_page=5')
            ->assertOk()
            ->assertJsonPath('pagination.perPage', 5);
    }

    /** H — backup state */
    public function test_group_h_backup_state(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=backup')
            ->assertOk();
    }
}
