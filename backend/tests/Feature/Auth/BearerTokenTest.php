<?php

namespace Tests\Feature\Auth;

use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §8 Bearer — POST /api/v1/auth/token (v19). */
class BearerTokenTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_token_endpoint_returns_bearer_token(): void
    {
        $res = $this->postJson('/api/v1/auth/token', [
            'username' => 'admin',
            'password' => 'changeme',
        ])->assertOk()->assertJsonPath('ok', true);

        $token = (string) $res->json('token');
        $this->assertNotEmpty($token);
        $this->assertSame('Bearer', $res->json('token_type'));

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me/state')
            ->assertOk()
            ->assertJsonPath('isLoggedIn', true);
    }

    public function test_token_invalid_credentials(): void
    {
        $this->postJson('/api/v1/auth/token', [
            'username' => 'admin',
            'password' => 'wrong',
        ])->assertStatus(401)->assertJsonPath('ok', false);
    }
}
