<?php

namespace Tests\Feature\Reseller;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ImpersonationHttpsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_impersonate_start_requires_https_in_production(): void
    {
        $this->app['env'] = 'production';
        $admin = DashboardUser::query()->where('username', 'admin')->first();
        $resellerSvpId = (int) SvpUser::query()->where('role', 'reseller')->value('id');

        $this->actingAs($admin)->postJson('/api/v1/dashboard/impersonate/start', [
            'reseller_svp_user_id' => $resellerSvpId,
        ])->assertStatus(403)
            ->assertJsonPath('message', 'https_required');
    }
}
