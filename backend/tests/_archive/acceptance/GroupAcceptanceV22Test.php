<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §14 groups A–H acceptance (v22). */
class GroupAcceptanceV22Test extends TestCase
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

    public function test_group_a_overview_json(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=dashboard')->assertOk()->json();
        $this->assertArrayHasKey('overview', $json);
        $this->assertArrayHasKey('stats', $json);
    }

    public function test_group_b_site_settings_subtabs(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=site_settings&site_subtab=purge_expired')
            ->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=site_settings&site_subtab=logs')
            ->assertOk();
    }

    public function test_group_c_users_list_json(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=users&users_page=1')->assertOk()->json();
        $this->assertArrayHasKey('usersList', $json);
    }

    public function test_group_d_bots_state(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=bots')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=reseller_bots')->assertOk();
    }

    public function test_group_e_panels_configs(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=xui_panels')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=configs')->assertOk();
        $this->actingAsReseller()->getJson('/api/v1/admin/state?activeTab=reseller_xui_panels')->assertOk();
    }

    public function test_group_f_receipts_json(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=receipts&receipts_page=1')->assertOk()->json();
        $this->assertArrayHasKey('receipts', $json);
    }

    public function test_group_g_broadcast_marketing(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=broadcast')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=marketing_lifecycle')->assertOk();
    }

    public function test_group_h_audit_l2tp_backup(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit?page=1&per_page=5&domain=security')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=l2tp_servers')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?activeTab=backup')->assertOk();
    }

    public function test_reseller_map_count_sixty_one(): void
    {
        $policy = new \App\Services\Mutations\MutatePolicyService;
        $ref = new \ReflectionClass($policy);
        $prop = $ref->getProperty('resellerMap');
        $prop->setAccessible(true);
        $this->assertCount(61, $prop->getValue($policy));
    }

    public function test_reseller_bot_ui_in_boot(): void
    {
        $tabs = collect($this->actingAsReseller()->getJson('/api/v1/bootstrap')->json('navTabs') ?? [])
            ->pluck('key')->all();
        $this->assertContains('bot_ui', $tabs);
    }
}
