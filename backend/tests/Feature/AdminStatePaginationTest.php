<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStatePaginationTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_pagination_uses_wp_keys(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=users');

        $response->assertOk()
            ->assertJsonStructure([
                'pagination' => [
                    'usersList' => ['page', 'perPage', 'total'],
                    'panels' => ['page', 'perPage', 'total'],
                ],
            ]);
    }
}
