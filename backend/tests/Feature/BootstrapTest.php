<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_returns_admin_fields(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('secret'),
            'role' => 'admin',
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/bootstrap');

        $response->assertOk()
            ->assertJsonPath('isAdmin', true)
            ->assertJsonPath('isLoggedIn', true)
            ->assertJsonStructure(['restUrl', 'features', 'navTabs', 'branding']);
    }
}
