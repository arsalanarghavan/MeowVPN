<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BootstrapOptionalAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_without_auth_returns_public_payload(): void
    {
        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('loginRequired', true)
            ->assertJsonPath('isLoggedIn', false)
            ->assertJsonStructure(['features', 'branding']);
    }
}
