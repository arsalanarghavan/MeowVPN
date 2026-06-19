<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminUserDetailTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_user_detail_returns_user(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/user/1');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('user.id', 1);
    }

    public function test_reseller_cannot_access_outsider(): void
    {
        $reseller = DashboardUser::query()->where('username', 'reseller')->first();
        $response = $this->actingAs($reseller)->getJson('/api/v1/admin/user/200');

        $response->assertStatus(403)
            ->assertJsonPath('message', 'forbidden');
    }
}
