<?php

namespace Tests\Feature\Portal;

use App\Models\DashboardUser;
use App\Models\SvpService;
use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class UserPortalApiTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_me_portal_returns_services_for_linked_user(): void
    {
        $svp = SvpUser::factory()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'role' => 'user',
        ]);
        SvpService::factory()->create([
            'user_id' => $svp->id,
            'display_label' => 'Test Svc',
            'total_traffic' => 10 * 1024 * 1024 * 1024,
            'used_traffic' => 2 * 1024 * 1024 * 1024,
        ]);

        $dash = DashboardUser::factory()->create([
            'username' => 'enduser',
            'role' => 'user',
            'svp_user_id' => $svp->id,
        ]);

        $this->actingAs($dash)
            ->getJson('/api/v1/me/portal')
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'services')
            ->assertJsonPath('services.0.display_label', 'Test Svc')
            ->assertJsonStructure(['portal_url', 'services' => [['portal_url', 'quota_gb']]]);
    }
}
