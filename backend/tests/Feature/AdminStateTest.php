<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStateTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_admin_state_returns_wp_shape_without_ok_wrapper(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();

        $response = $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=dashboard');

        $response->assertOk()
            ->assertJsonStructure(['settings', 'navTabs', 'overview', 'pagination', 'usersList']);

        $this->assertArrayNotHasKey('ok', $response->json());
    }
}
