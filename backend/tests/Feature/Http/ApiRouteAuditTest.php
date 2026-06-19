<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §7 — admin API route matrix (v28 alias parity). */
class ApiRouteAuditTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('xui_panel', true);
        $this->setModuleEnabled('backup', true);
        $this->setModuleEnabled('marketing', true);
    }

    public function test_admin_state_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state')->assertOk();
    }

    public function test_admin_mutate_route(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'general',
        ])->assertOk();
    }

    public function test_bootstrap_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/bootstrap')->assertOk();
    }

    public function test_me_state_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/me/state')->assertOk();
    }

    public function test_admin_user_search_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/user-search?q=child')->assertOk();
    }

    public function test_admin_user_show_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/user/101')->assertOk();
    }

    public function test_admin_audit_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/audit')->assertOk();
    }

    public function test_admin_logs_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/logs')->assertOk();
    }

    public function test_admin_purge_expired_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/purge-expired')->assertOk();
    }

    public function test_admin_backup_routes(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/backups')->assertOk();
        $this->actingAsAdmin()->getJson('/api/v1/admin/backup/status')->assertOk();
    }

    public function test_admin_configs_routes(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/configs-snapshot?panel_id=1')->assertOk();
    }

    public function test_admin_broadcast_queue_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/broadcast-queue')->assertOk();
    }

    public function test_impersonate_start_stop_aliases(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/stop')->assertOk();
    }

    public function test_dashboard_impersonate_session_paths(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/dashboard/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();
        $this->actingAsAdmin()->postJson('/api/v1/dashboard/impersonate/stop')->assertOk();
    }

    public function test_inbound_display_catalog_reseller_forbidden_out_of_scope_panel(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/inbound-display-catalog?panel_id=999')
            ->assertForbidden();
    }

    public function test_inbound_display_catalog_admin_ok(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/inbound-display-catalog?panel_id=1')
            ->assertOk();
    }

    public function test_health_and_metrics_routes(): void
    {
        $this->getJson('/health')->assertOk();
        $this->getJson('/health/ready')->assertOk();
        $this->get('/metrics')->assertOk();
    }

    public function test_portal_subscription_and_avatar_routes(): void
    {
        $this->get('/sub/invalid-token')->assertOk()->assertJsonPath('ok', true);
        $this->get('/info')->assertOk();
        $this->get('/api/v1/portal/tg-avatar?svp_u=1&target_uid=1')->assertStatus(403);
    }

    public function test_me_portal_route(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/me/portal')->assertOk();
    }

    public function test_dashboard_admin_alias_state_matches_canonical(): void
    {
        $canonical = $this->actingAsAdmin()->getJson('/api/v1/admin/state');
        $alias = $this->actingAsAdmin()->getJson('/api/v1/dashboard/admin/state');
        $alias->assertOk();
        $this->assertSame($canonical->json(), $alias->json());
    }

    public function test_dashboard_admin_alias_mutate_matches_canonical(): void
    {
        $payload = ['op' => 'settings_tab', 'tab' => 'general'];
        $canonical = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', $payload);
        $alias = $this->actingAsAdmin()->postJson('/api/v1/dashboard/admin/mutate', $payload);
        $alias->assertOk();
        $this->assertSame($canonical->json('ok'), $alias->json('ok'));
    }

    private function assertAliasReadParity(string $path): void
    {
        $canonical = $this->actingAsAdmin()->getJson('/api/v1/admin/'.$path);
        $alias = $this->actingAsAdmin()->getJson('/api/v1/dashboard/admin/'.$path);
        $alias->assertStatus($canonical->getStatusCode());
        if ($canonical->isOk()) {
            $this->assertSame($canonical->json(), $alias->json());
        }
    }

    public function test_dashboard_admin_alias_reads_match_canonical(): void
    {
        foreach ([
            'state',
            'audit',
            'logs',
            'purge-expired',
            'user-search?q=child',
            'user/101',
            'users-bulk-jobs',
            'users-bulk-job-items',
            'inbound-display-catalog?panel_id=1',
            'configs-snapshot?panel_id=1',
            'configs-portal-payload?panel_id=1',
            'panel-inbounds?panel_id=1',
            'panel-inbound-clients?panel_id=1&inbound_id=1',
            'panel/inbound-map?panel_id=1',
            'broadcast-queue',
            'backups',
            'backup/status',
        ] as $path) {
            $this->assertAliasReadParity($path);
        }
    }

    private function assertAliasWriteParity(string $method, string $path, array $payload = []): void
    {
        $canonical = $this->actingAsAdmin()->json($method, '/api/v1/admin/'.$path, $payload);
        $alias = $this->actingAsAdmin()->json($method, '/api/v1/dashboard/admin/'.$path, $payload);
        $alias->assertStatus($canonical->getStatusCode());
        if ($canonical->isOk()) {
            $this->assertSame($canonical->json('ok'), $alias->json('ok'));
        }
    }

    public function test_dashboard_admin_alias_writes_match_canonical(): void
    {
        $this->assertAliasWriteParity('POST', 'mutate', ['op' => 'settings_tab', 'tab' => 'general']);
        $this->assertAliasWriteParity('POST', 'configs-sync', ['panel_id' => 1]);
        $this->assertAliasWriteParity('POST', 'backup/run', []);
        $this->assertAliasWriteParity('POST', 'backup/reset-stuck', []);
        $this->assertAliasWriteParity('POST', 'panel/inbound-map', ['panel_id' => 1, 'map' => []]);
        $this->assertAliasWriteParity('POST', 'impersonate/start', ['targetSvpUserId' => 100]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/stop')->assertOk();
        $this->assertAliasWriteParity('POST', 'impersonate/stop', []);
    }

    public function test_dashboard_session_routes_registered(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/dashboard/persona', ['persona' => 'admin'])->assertOk();
        $this->actingAsAdmin()->postJson('/api/v1/dashboard/ui-preferences', [
            'ui_theme' => 'dark',
            'ui_accent' => 'blue',
        ])->assertOk();
    }
}
