<?php

namespace Tests\Feature\Acceptance;

use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §14 groups A–H acceptance (v21). */
class GroupAcceptanceV21Test extends TestCase
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

    public function test_overview_stats_structure(): void
    {
        $json = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?activeTab=dashboard&overview_metrics_window_days=7')
            ->assertOk()
            ->json();
        $this->assertArrayHasKey('overview', $json);
        $this->assertArrayHasKey('users_total', $json['overview']);
        $this->assertArrayHasKey('receipts_pending', $json['overview']);
        $this->assertArrayHasKey('panels_total', $json['overview']);
        $this->assertSame(7, $json['stats']['window_days'] ?? 0);
    }

    public function test_reseller_bots_xui_admin_only_in_boot(): void
    {
        $tabs = collect($this->actingAsReseller()->getJson('/api/v1/bootstrap')->json('navTabs') ?? [])
            ->pluck('key')
            ->all();
        $this->assertNotContains('bots', $tabs);
        $this->assertNotContains('xui_panels', $tabs);
        $this->assertContains('reseller_xui_panels', $tabs);
    }

    public function test_broadcast_tab_blocked_when_marketing_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=broadcast')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_marketing_lifecycle_mutate_reseller_forbidden(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_save',
            'segment_key' => 'never_purchased',
            'enabled' => true,
        ])->assertForbidden()->assertJsonPath('message', 'forbidden_op');
    }

    public function test_reseller_overview_scoped(): void
    {
        SvpUser::query()->create([
            'username' => 'outside_tree_v21',
            'role' => 'user',
            'status' => 'approved',
            'invited_by' => 0,
            'created_at' => now(),
        ]);
        $admin = (int) ($this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=dashboard')->json('overview.users_total') ?? 0);
        $reseller = (int) ($this->actingAsReseller()->getJson('/api/v1/admin/state?activeTab=dashboard')->json('overview.users_total') ?? 0);
        $this->assertLessThanOrEqual($admin, $reseller);
    }

    public function test_audit_impersonation_domain(): void
    {
        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/audit?page=1&per_page=5&domain=security')
            ->assertOk()
            ->assertJsonStructure(['rows', 'pagination']);
    }
}
