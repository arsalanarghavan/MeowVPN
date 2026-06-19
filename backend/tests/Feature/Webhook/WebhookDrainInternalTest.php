<?php

namespace Tests\Feature\Webhook;

use App\Services\Bot\InboundQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookDrainInternalTest extends TestCase
{
    use RefreshDatabase;

    public function test_drain_rejects_external_ip(): void
    {
        config(['svp.queue_drain_key' => 'test-drain-key']);

        $this->postJson('/api/v1/webhook-queue/drain', [], [
            'X-SVP-QUEUE-KEY' => 'test-drain-key',
            'REMOTE_ADDR' => '203.0.113.10',
        ])->assertForbidden();
    }

    public function test_drain_accepts_loopback_with_valid_key(): void
    {
        config(['svp.queue_drain_key' => 'test-drain-key']);
        $this->mock(InboundQueueService::class, function ($mock) {
            $mock->shouldReceive('internalQueueKey')->andReturn('test-drain-key');
            $mock->shouldReceive('drainBatch')->once()->andReturn(0);
        });

        $this->call(
            'POST',
            '/api/v1/webhook-queue/drain',
            [],
            [],
            [],
            [
                'HTTP_X_SVP_QUEUE_KEY' => 'test-drain-key',
                'REMOTE_ADDR' => '127.0.0.1',
            ]
        )->assertOk()->assertJsonPath('ok', true);
    }
}
