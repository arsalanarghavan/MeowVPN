<?php

namespace Tests\Feature\Http;

use Tests\TestCase;

/** §7.2 — dashboard/admin/* alias routes (Laravel + nginx). */
class DashboardNginxAliasTest extends TestCase
{
    public function test_dashboard_admin_state_alias_is_not_404(): void
    {
        $response = $this->getJson('/api/v1/dashboard/admin/state');

        $this->assertNotSame(404, $response->getStatusCode());
        $this->assertContains($response->getStatusCode(), [401, 403]);
    }

    public function test_canonical_admin_state_same_behavior(): void
    {
        $alias = $this->getJson('/api/v1/dashboard/admin/state');
        $canonical = $this->getJson('/api/v1/admin/state');

        $this->assertSame($canonical->getStatusCode(), $alias->getStatusCode());
    }

    public function test_dashboard_admin_mutate_alias_same_status_as_canonical(): void
    {
        $payload = ['op' => 'settings_tab', 'tab' => 'general'];
        $alias = $this->postJson('/api/v1/dashboard/admin/mutate', $payload);
        $canonical = $this->postJson('/api/v1/admin/mutate', $payload);

        $this->assertSame($canonical->getStatusCode(), $alias->getStatusCode());
    }

    public function test_dashboard_admin_backup_status_alias_same_status_as_canonical(): void
    {
        $alias = $this->getJson('/api/v1/dashboard/admin/backup/status');
        $canonical = $this->getJson('/api/v1/admin/backup/status');

        $this->assertSame($canonical->getStatusCode(), $alias->getStatusCode());
    }

    public function test_dashboard_session_paths_are_not_404(): void
    {
        foreach (['/api/v1/dashboard/persona', '/api/v1/dashboard/ui-preferences'] as $url) {
            $response = $this->postJson($url, []);
            $this->assertNotSame(404, $response->getStatusCode());
        }
    }

    public function test_dashboard_impersonate_paths_are_not_404(): void
    {
        foreach (['/api/v1/dashboard/impersonate/start', '/api/v1/dashboard/impersonate/stop'] as $url) {
            $response = $this->postJson($url, []);
            $this->assertNotSame(404, $response->getStatusCode());
            $this->assertContains($response->getStatusCode(), [401, 403, 422]);
        }
    }
}
