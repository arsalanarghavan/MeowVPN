<?php

namespace Tests\Feature\Http;

use App\Models\DashboardUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Services\SettingsStore;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class Wave456EndpointsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
        $this->setModuleEnabled('xui_panel', true);
    }

    protected function admin(): DashboardUser
    {
        return DashboardUser::query()->where('username', 'admin')->firstOrFail();
    }

    public function test_dashboard_login_get(): void
    {
        $this->getJson('/api/v1/dashboard/login')->assertOk()->assertJsonPath('ok', true);
    }

    public function test_cron_status_requires_admin(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->getJson('/api/v1/admin/cron-status')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['session_keeper_rest_url', 'internal_secret_configured']);
    }

    public function test_orphan_clients_scan_validation(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->postJson('/api/v1/admin/panel/orphan-clients/scan', ['panel_id' => 0])
            ->assertStatus(400)
            ->assertJsonPath('message', 'bad_panel');
    }

    public function test_configs_live_traffic_validation(): void
    {
        $this->actingAs($this->admin(), 'web')
            ->postJson('/api/v1/admin/configs-live-traffic', ['panel_id' => 0, 'items' => []])
            ->assertStatus(400)
            ->assertJsonPath('message', 'bad_panel');
    }

    public function test_internal_session_keeper_requires_secret(): void
    {
        $this->postJson('/api/v1/internal/session-keeper')->assertStatus(503);
    }

    public function test_internal_session_keeper_with_secret(): void
    {
        app(SettingsStore::class)->set('internal_cron_secret', 'test-secret-123');
        $this->withHeader('X-SVP-Internal-Secret', 'test-secret-123')
            ->postJson('/api/v1/internal/session-keeper')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_portal_usage_requires_signature(): void
    {
        $this->getJson('/api/v1/portal/usage')->assertStatus(403);
    }

    public function test_portal_usage_with_signature(): void
    {
        $link = app(PortalLinkService::class)->buildPortalLink(101, 3600, 1);
        $this->getJson('/api/v1/portal/usage?'.http_build_query([
            'svp_u' => $link['svp_u'],
            'svp_e' => $link['svp_e'],
            'svp_s' => $link['svp_s'],
            'service_id' => 1,
            'range' => '7d',
        ]))->assertOk()->assertJsonStructure(['points', 'total_in_range']);
    }
}
