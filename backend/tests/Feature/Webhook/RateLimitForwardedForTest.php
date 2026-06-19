<?php

namespace Tests\Feature\Webhook;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §13 — rate_limit_trust_forwarded_for config (v13). */
class RateLimitForwardedForTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        config(['svp.webhook_rate_limit_per_min' => 1]);
        app(SettingsStore::class)->merge([
            'telegram_webhook_secret' => 'sec',
            'bot_enabled' => true,
            'telegram_enabled' => true,
            'rate_limit_trust_forwarded_for' => true,
        ]);
    }

    public function test_forwarded_ip_gets_separate_rate_bucket(): void
    {
        $payload = ['update_id' => 1, 'message' => ['from' => ['id' => 1], 'chat' => ['id' => 1]]];

        $this->postJson('/api/v1/webhook/telegram/sec', $payload, [
            'X-Forwarded-For' => '203.0.113.10',
        ])->assertOk();

        $this->postJson('/api/v1/webhook/telegram/sec', $payload, [
            'X-Forwarded-For' => '203.0.113.11',
        ])->assertOk();
    }
}
