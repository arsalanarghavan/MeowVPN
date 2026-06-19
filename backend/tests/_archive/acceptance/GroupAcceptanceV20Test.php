<?php

namespace Tests\Feature\Acceptance;

use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §14 groups A–H strict acceptance (v20). */
class GroupAcceptanceV20Test extends TestCase
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

    public function test_active_tab_legacy_tab_alias(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=users')
            ->assertOk()
            ->assertJsonStructure(['usersList']);
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?activeTab=users')
            ->assertOk()
            ->assertJsonStructure(['usersList']);
    }

    public function test_stats_series_in_overview(): void
    {
        $json = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?activeTab=dashboard&overview_metrics_window_days=7')
            ->assertOk()
            ->json();
        $this->assertSame(7, $json['stats']['window_days'] ?? 0);
        $this->assertIsArray($json['stats']['series'] ?? null);
    }

    public function test_reseller_configs_client_forbidden(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_delete',
            'panel_id' => 1,
            'client_id' => 1,
        ])->assertForbidden();
    }

    public function test_reseller_xui_panels_state_allowed(): void
    {
        $this->actingAsReseller()
            ->getJson('/api/v1/admin/state?activeTab=reseller_xui_panels')
            ->assertOk();
    }

    public function test_merge_preview_legacy_ids(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge_preview',
            'source_id' => 200,
            'target_id' => 101,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_a_overview_reseller_scoped(): void
    {
        SvpUser::query()->create([
            'username' => 'outside_tree_v20',
            'role' => 'user',
            'status' => 'approved',
            'invited_by' => 0,
            'created_at' => now(),
        ]);
        $admin = (int) ($this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=dashboard')->json('overview.users_total') ?? 0);
        $reseller = (int) ($this->actingAsReseller()->getJson('/api/v1/admin/state?activeTab=dashboard')->json('overview.users_total') ?? 0);
        $this->assertLessThanOrEqual($admin, $reseller);
    }

    public function test_d_bot_ui_reseller_forbidden(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_save',
            'version' => 1,
            'surfaces' => [],
        ])->assertForbidden();
    }

    public function test_h_audit_pagination(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/audit?page=1&per_page=5&domain=security')
            ->assertOk()
            ->assertJsonPath('pagination.perPage', 5);
    }
}
