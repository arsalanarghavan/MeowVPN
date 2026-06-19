<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminDashboardRateLimitTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
        config(['svp.admin_state_rate_limit_per_min' => 2]);
        config(['svp.admin_mutate_rate_limit_per_min' => 2]);
        Cache::flush();
    }

    public function test_admin_state_rate_limited(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=dashboard')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=dashboard')->assertOk();
        $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=dashboard')->assertStatus(429)
            ->assertJson(['ok' => false, 'message' => 'rate_limited']);
    }

    public function test_admin_mutate_rate_limited(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $payload = ['op' => 'noop'];
        $this->actingAs($user)->postJson('/api/v1/admin/mutate', $payload);
        $this->actingAs($user)->postJson('/api/v1/admin/mutate', $payload);
        $this->actingAs($user)->postJson('/api/v1/admin/mutate', $payload)
            ->assertStatus(429)
            ->assertJson(['ok' => false, 'message' => 'rate_limited']);
    }

    public function test_default_admin_state_rate_limit_is_sixty_per_min(): void
    {
        $this->assertSame(60, (int) config('svp.admin_state_rate_limit_per_min'));
    }
}
