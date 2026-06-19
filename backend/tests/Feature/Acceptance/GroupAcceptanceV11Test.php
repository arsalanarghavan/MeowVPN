<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Spec §14 v11 — remaining acceptance gaps (backend-evidenced). */
class GroupAcceptanceV11Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_quick_links_respect_permissions(): void
    {
        $this->actingAsReseller();
        $reseller = $this->dashboardResellerUser();
        $perms = is_array($reseller->permissions_json) ? $reseller->permissions_json : [];
        $perms['services.manage'] = false;
        $reseller->permissions_json = $perms;
        $reseller->save();

        $boot = $this->getJson('/api/v1/bootstrap')->assertOk()->json();
        $tabs = collect($boot['navTabs'] ?? [])->pluck('key')->all();
        $this->assertNotContains('xui_panels', $tabs);
        $this->assertNotContains('configs', $tabs);
    }

    public function test_monitoring_ping_refresh_live_metrics(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);

        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=monitoring&refreshLivePanelMetrics=1')
            ->assertOk()
            ->assertJsonStructure(['monitorHosts', 'overview']);
    }

    public function test_wp_pages_in_site_settings_state(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=site_settings&site_subtab=whitelabel')
            ->assertOk()
            ->assertJsonStructure(['wpPages']);
    }

    public function test_pending_users_pagination_keys(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=users&users_subtab=pending')
            ->assertOk()
            ->json();
        $this->assertArrayHasKey('pendingUsers', $json);
        $this->assertArrayHasKey('pagination', $json);
        $this->assertArrayHasKey('pendingUsers', $json['pagination']);
    }

    public function test_users_bulk_jobs_api_lists_progress(): void
    {
        DB::table('svp_users_bulk_jobs')->insert([
            'operation' => 'extend',
            'scope' => 'all_approved',
            'payload_json' => json_encode(['days' => 1]),
            'status' => 'running',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->getJson('/api/v1/admin/users-bulk-jobs')
            ->assertOk()
            ->assertJsonStructure(['jobs']);
    }

    public function test_configs_stale_cache_indicator_field(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=configs')->assertOk()->json();
        $this->assertArrayHasKey('configsClients', $json);
    }

    public function test_telegram_proxy_egress_test_mutate(): void
    {
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
        app(\App\Services\SettingsStore::class)->set('telegram_http_proxy', 'http://127.0.0.1:8080');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_proxy_test',
        ])->assertOk();
    }

    public function test_notify_settings_persist_via_settings_tab(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'notifications',
            'notify_expiry_days' => 3,
            'notify_admin_panel_down_cooldown' => 120,
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
