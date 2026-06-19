<?php

namespace Tests\Feature\Webhook;

use App\Services\Bot\InboundQueueService;
use Tests\TestCase;

class QueueDrainKeyTest extends TestCase
{
    public function test_env_queue_drain_key_takes_precedence(): void
    {
        config(['svp.queue_drain_key' => 'deploy-time-key']);
        $this->assertSame('deploy-time-key', app(InboundQueueService::class)->internalQueueKey());
    }
}
