<?php

namespace Tests\Feature\Webhook;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class TelegramSecretTokenHeaderTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $s = app(SettingsStore::class);
        $s->set('telegram_webhook_secret', 'tg-sec');
        $s->set('telegram_secret_header', 'hdr-token');
        $s->set('bot_enabled', true);
        $s->set('telegram_enabled', true);
    }

    public function test_missing_secret_token_header_returns_403(): void
    {
        $this->postJson('/api/v1/webhook/telegram/tg-sec', ['update_id' => 1])
            ->assertForbidden();
    }

    public function test_valid_secret_token_header_accepted(): void
    {
        $this->postJson('/api/v1/webhook/telegram/tg-sec', ['update_id' => 2], [
            'X-Telegram-Bot-Api-Secret-Token' => 'hdr-token',
        ])->assertOk();
    }
}
