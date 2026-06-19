<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class RedactSecretsMiddlewareTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_mutate_request_logs_redact_panel_password(): void
    {
        $logged = null;
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->andReturnUsing(function ($message, $context = []) use (&$logged) {
            if ($message === 'request') {
                $logged = $context;
            }
        });
        Log::shouldReceive('debug')->andReturnNull();
        Log::shouldReceive('warning')->andReturnNull();
        Log::shouldReceive('error')->andReturnNull();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'label' => 'Log Test',
            'panel_url' => 'https://log.test',
            'panel_password' => 'super-secret-pass',
        ]);

        $this->assertIsArray($logged);
        $payload = json_encode($logged['payload'] ?? [], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('[redacted]', (string) $payload);
        $this->assertStringNotContainsString('super-secret-pass', (string) $payload);
    }
}
