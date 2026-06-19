<?php

namespace Tests\Feature;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BotWebhookMutateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        app(SettingsStore::class)->set('telegram_bot_token', '123:ABC');
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200),
        ]);
    }

    public function test_set_webhook_mutate(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', ['op' => 'bot_set_webhook'])
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertNotEmpty(app(SettingsStore::class)->get('telegram_webhook_secret'));
    }

    public function test_delete_webhook_mutate(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', ['op' => 'bot_delete_webhook'])
            ->assertOk();
    }
}
