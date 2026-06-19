<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStatePanelsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_panels_tab_returns_data_and_pagination(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $response = $this->actingAs($user)->getJson('/api/v1/admin/state?activeTab=xui_panels&panels_page=1');

        $response->assertOk()
            ->assertJsonPath('pagination.panels.total', 1)
            ->assertJsonCount(1, 'panels');
    }
}
