<?php

namespace Tests\Feature;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WebhookIngressTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->set('telegram_webhook_secret', 'test-secret');
        app(SettingsStore::class)->set('bot_enabled', true);
        app(SettingsStore::class)->set('telegram_enabled', true);
    }

    public function test_invalid_secret_returns_403(): void
    {
        $this->postJson('/api/v1/webhook/telegram/wrong', ['update_id' => 1])
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden');
    }

    public function test_valid_webhook_enqueues_and_returns_200(): void
    {
        $this->postJson('/api/v1/webhook/telegram/test-secret', [
            'update_id' => 42,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 999, 'first_name' => 'T'],
                'chat' => ['id' => 999, 'type' => 'private'],
                'text' => '/start',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('svp_inbound_queue', [
            'platform' => 'telegram',
            'status' => 'pending',
        ]);
    }

    public function test_drain_requires_queue_key(): void
    {
        $this->postJson('/api/v1/webhook-queue/drain')->assertForbidden();
    }
}
