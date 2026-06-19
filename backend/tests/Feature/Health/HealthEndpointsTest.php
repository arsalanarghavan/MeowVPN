<?php

namespace Tests\Feature\Health;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class HealthEndpointsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_health_live_returns_ok(): void
    {
        $this->getJson('/health')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_health_ready_returns_checks(): void
    {
        $response = $this->getJson('/health/ready');
        $response->assertJsonStructure(['ok', 'checks' => ['database', 'cache']]);
        $this->assertTrue($response->json('checks.database'));
    }
}
