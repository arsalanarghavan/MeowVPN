<?php

namespace Tests\Feature\Telegram;

use App\Models\SvpTelegramMirrorBot;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class TelegramMirrorWebhookTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected int $mirrorId;

    protected string $secret = 'mirror-webhook-secret';

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->set('bot_enabled', true);
        app(SettingsStore::class)->set('telegram_enabled', true);

        $this->mirrorId = (int) SvpTelegramMirrorBot::query()->insertGetId([
            'label' => 'WH Mirror',
            'telegram_token' => Crypt::encryptString('123:ABC'),
            'telegram_bot_username' => 'wh_mirror',
            'webhook_secret' => Crypt::encryptString($this->secret),
            'telegram_secret_token' => '',
            'enabled' => true,
            'sort_order' => 0,
            'updated_at' => now(),
        ]);
    }

    public function test_get_health_check_returns_alive_payload(): void
    {
        $this->getJson("/api/v1/webhook/telegram/mirror/{$this->mirrorId}/{$this->secret}")
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'alive' => true,
                'scope' => 'mirror',
            ]);
    }

    public function test_invalid_secret_returns_403(): void
    {
        $this->postJson("/api/v1/webhook/telegram/mirror/{$this->mirrorId}/wrong-secret", [
            'update_id' => 1,
        ])->assertForbidden();
    }

    public function test_valid_post_enqueues_mirror_update(): void
    {
        $this->postJson("/api/v1/webhook/telegram/mirror/{$this->mirrorId}/{$this->secret}", [
            'update_id' => 77,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 999, 'first_name' => 'T'],
                'chat' => ['id' => 999, 'type' => 'private'],
                'text' => '/start',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('svp_inbound_queue', [
            'platform' => 'telegram',
            'mirror_bot_id' => $this->mirrorId,
        ]);
    }

    public function test_disabled_mirror_returns_disabled_without_enqueue(): void
    {
        SvpTelegramMirrorBot::query()->where('id', $this->mirrorId)->update(['enabled' => false]);

        $this->postJson("/api/v1/webhook/telegram/mirror/{$this->mirrorId}/{$this->secret}", [
            'update_id' => 88,
        ])->assertOk()->assertJson(['ok' => true, 'disabled' => true]);

        $this->assertDatabaseMissing('svp_inbound_queue', [
            'mirror_bot_id' => $this->mirrorId,
            'status' => 'pending',
        ]);
    }
}
