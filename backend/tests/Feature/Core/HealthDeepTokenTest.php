<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class HealthDeepTokenTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        config(['svp.health_deep_token' => 'deep-test-token']);
    }

    public function test_health_deep_requires_token_when_configured(): void
    {
        $this->getJson('/health/deep')
            ->assertStatus(403)
            ->assertJsonPath('message', 'forbidden');

        $this->getJson('/health/deep', ['X-Health-Token' => 'deep-test-token'])
            ->assertOk();
    }
}
