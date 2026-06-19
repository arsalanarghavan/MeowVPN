<?php

namespace Tests\Feature\Internal;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class InternalBotApiTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        config(['svp.bot_service_secret' => 'test-bot-secret']);
    }

    public function test_health_requires_service_secret(): void
    {
        $this->getJson('/api/v1/internal/bot/health')
            ->assertStatus(403);

        $this->withHeaders([
            'X-SVP-Bot-Service-Secret' => 'test-bot-secret',
            'X-SVP-Platform' => 'telegram',
        ])->getJson('/api/v1/internal/bot/health')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_process_update_queues_telegram_payload(): void
    {
        $res = $this->withHeaders([
            'X-SVP-Bot-Service-Secret' => 'test-bot-secret',
            'X-SVP-Platform' => 'telegram',
        ])->postJson('/api/v1/internal/bot/process-update', [
            'update_id' => 9001,
            'message' => ['message_id' => 1, 'text' => '/start'],
            'async' => true,
        ]);

        $res->assertOk()->assertJsonPath('ok', true)->assertJsonPath('queued', true);
    }

    public function test_settings_endpoint_returns_keys(): void
    {
        app(SettingsStore::class)->set('bot_enabled', true);

        $this->withHeaders([
            'X-SVP-Bot-Service-Secret' => 'test-bot-secret',
            'X-SVP-Platform' => 'bale',
        ])->getJson('/api/v1/internal/bot/settings?keys[]=bot_enabled')
            ->assertOk()
            ->assertJsonPath('settings.bot_enabled', true);
    }
}
