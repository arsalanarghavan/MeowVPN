<?php

namespace Tests\Feature\Auth;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_logout_invalidates_session(): void
    {
        $user = DashboardUser::query()->create([
            'username' => 'admin',
            'password' => Hash::make('changeme'),
            'role' => 'admin',
        ]);
        $this->actingAs($user);

        $this->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('ok', true);
    }
}
