<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use App\Services\AdminState\PayloadDefaults;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStatePayloadShapeTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_payload_contains_all_root_keys(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=dashboard');
        $response->assertOk();

        $expected = array_keys(PayloadDefaults::root());
        $expected[] = 'pagination';
        $expected[] = 'resellerContextId';

        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $response->json(), "Missing key: {$key}");
        }
    }
}
