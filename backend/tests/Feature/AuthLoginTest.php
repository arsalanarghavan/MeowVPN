<?php

namespace Tests\Feature;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_success_returns_redirect(): void
    {
        DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('changeme'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'log' => 'admin',
            'pwd' => 'changeme',
        ]);

        $response->assertOk()
            ->assertJson(['ok' => true])
            ->assertJsonStructure(['redirect']);
        $this->assertAuthenticatedAs(DashboardUser::query()->first());
    }

    public function test_login_fails_with_bad_password(): void
    {
        DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('changeme'),
            'role' => 'admin',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'log' => 'admin',
            'pwd' => 'wrong',
        ]);

        $response->assertUnauthorized()
            ->assertJsonPath('message', 'invalid_credentials');
    }
}
