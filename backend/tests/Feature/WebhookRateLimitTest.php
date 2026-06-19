<?php

namespace Tests\Feature;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WebhookRateLimitTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $settings = app(SettingsStore::class);
        $settings->set('telegram_webhook_secret', 'sec');
        $settings->set('webhook_rate_limit_per_min', 2);
        $settings->set('bot_enabled', true);
        $settings->set('telegram_enabled', true);
    }

    public function test_rate_limit_returns_429(): void
    {
        $payload = ['update_id' => 1, 'message' => ['from' => ['id' => 1], 'chat' => ['id' => 1]]];
        $this->postJson('/api/v1/webhook/telegram/sec', $payload)->assertOk();
        $this->postJson('/api/v1/webhook/telegram/sec', $payload)->assertOk();
        $this->postJson('/api/v1/webhook/telegram/sec', $payload)
            ->assertStatus(429)
            ->assertJsonPath('message', 'rate_limited');
    }
}
