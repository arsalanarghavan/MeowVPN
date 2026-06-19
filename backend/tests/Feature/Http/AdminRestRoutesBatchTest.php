<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** REST routes missing dedicated tests (v14). */
class AdminRestRoutesBatchTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('backup', true);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_media_upload_requires_file(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/media', [])
            ->assertStatus(422);
    }

    public function test_panel_inbound_map_post(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/panel/inbound-map', [
            'panel_id' => 1,
            'map' => [],
        ])->assertOk();
    }

    public function test_panel_rebuild_from_db(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/panel/rebuild-from-db', [
            'panel_id' => 1,
        ])->assertOk();
    }

    public function test_panel_fix_51200_traffic(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/panel/fix-51200-traffic', [
            'panel_id' => 1,
        ])->assertOk();
    }

    public function test_backup_reset_stuck(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/backup/reset-stuck')
            ->assertOk();
    }

    public function test_backup_restore_upload_requires_file(): void
    {
        $this->actingAsAdmin()->post('/api/v1/admin/backup/restore-upload', [
            'confirm' => '1',
        ])->assertStatus(422);
    }

    public function test_panel_inbounds_list(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/panel-inbounds?panel_id=1')
            ->assertOk();
    }

    public function test_panel_inbound_clients_list(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/panel-inbound-clients?panel_id=1&inbound_id=1')
            ->assertOk();
    }

    public function test_configs_portal_payload(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/configs-portal-payload?panel_id=1')
            ->assertOk();
    }

    public function test_users_bulk_job_items(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/users-bulk-job-items?job_id=1')
            ->assertOk();
    }
}
