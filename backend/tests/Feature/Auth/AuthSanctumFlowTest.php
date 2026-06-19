<?php

namespace Tests\Feature\Auth;

use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AuthSanctumFlowTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_csrf_login_then_me_state_authenticated(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertOk();

        $this->postJson('/api/v1/auth/login', [
            'log' => 'admin',
            'pwd' => 'changeme',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->getJson('/api/v1/me/state')
            ->assertOk()
            ->assertJsonPath('isLoggedIn', true);
    }
}
