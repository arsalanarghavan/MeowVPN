<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminUserSearchTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_user_search_returns_matches(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/user-search?q=child');

        $response->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonCount(1, 'users');
    }
}
