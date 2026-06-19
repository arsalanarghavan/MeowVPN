<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStateResellerScopeTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_reseller_sees_only_downline_users(): void
    {
        $reseller = DashboardUser::query()->where('username', 'reseller')->first();
        $response = $this->actingAs($reseller)->getJson('/api/v1/admin/state?activeTab=users');

        $response->assertOk();
        $ids = collect($response->json('usersList'))->pluck('id')->all();
        $this->assertContains(101, $ids);
        $this->assertNotContains(200, $ids);
    }

    public function test_reseller_forbidden_tab(): void
    {
        $reseller = DashboardUser::query()->where('username', 'reseller')->first();
        $response = $this->actingAs($reseller)->getJson('/api/v1/admin/state?activeTab=site_settings');

        $response->assertStatus(403)
            ->assertJson(['ok' => false, 'message' => 'forbidden_tab']);
    }
}
