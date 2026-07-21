<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class MutateBotSiteDepthTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bot_set_and_delete_webhook(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        app(SettingsStore::class)->set('telegram_bot_token', '123:ABC');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_set_webhook',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_delete_webhook',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_bot_set_update_mode_polling_and_webhook(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $settings = app(SettingsStore::class);
        $settings->set('telegram_bot_token', '123:ABC');
        $settings->set('telegram_webhook_secret', 'sec');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_set_update_mode',
            'platform' => 'telegram',
            'mode' => 'polling',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('mode', 'polling');

        $this->assertSame('polling', $settings->get('telegram_update_mode'));

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_set_update_mode',
            'platform' => 'telegram',
            'mode' => 'webhook',
        ])->assertOk()->assertJsonPath('ok', true)->assertJsonPath('mode', 'webhook');

        $this->assertSame('webhook', $settings->get('telegram_update_mode'));
    }

    public function test_bot_toggle_platform_enabled(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_toggle_platform_enabled',
            'platform' => 'telegram',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_bot_ops_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_diagnostics',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_bot_admin_id_add_and_remove(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_admin_id_add',
            'id' => 999888777,
            'platform' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_admin_id_remove',
            'id' => 999888777,
            'platform' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_bot_toggle_enabled(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_toggle_enabled',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertTrue((bool) app(\App\Services\SettingsStore::class)->get('bot_enabled', false));
    }

    public function test_bot_test_telegram_calls_get_me(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200)]);
        app(SettingsStore::class)->set('telegram_bot_token', '123:ABC');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_test_telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/getMe'));
    }

    public function test_bot_test_telegram_rejects_missing_token(): void
    {
        app(SettingsStore::class)->set('telegram_bot_token', '');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_test_telegram',
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_bot_ui_layout_save_and_reset(): void
    {
        $surfaces = [
            'user_main' => [[
                ['id' => 'user.main.buy', 'enabled' => true, 'glass' => false],
            ]],
        ];
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_save',
            'surfaces' => $surfaces,
        ])->assertOk()->assertJsonPath('ok', true);

        $stored = app(SettingsStore::class)->get('bot_ui_layout', []);
        $this->assertSame($surfaces['user_main'], $stored['surfaces']['user_main'] ?? null);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_reset',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame([], app(SettingsStore::class)->get('bot_ui_layout'));
    }

    public function test_texts_save_reset_one_and_reset_all(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_save',
            'texts' => [
                'btn.main.buy' => ['fa' => 'خرید سفارشی', 'en' => 'Custom buy'],
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_texts', [
            'key_name' => 'btn.main.buy',
            'locale' => 'fa',
            'value' => 'خرید سفارشی',
        ]);
        $this->assertDatabaseHas('svp_texts', [
            'key_name' => 'btn.main.buy',
            'locale' => 'en',
            'value' => 'Custom buy',
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'text_reset_one',
            'text_key' => 'btn.main.buy',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_texts', [
            'key_name' => 'btn.main.buy',
            'locale' => 'en',
            'value' => '🛒 Buy service',
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_save',
            'key' => 'welcome.custom',
            'value' => 'Hello v12',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_reset',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertGreaterThan(100, \Illuminate\Support\Facades\DB::table('svp_texts')->count());
        $this->assertDatabaseMissing('svp_texts', ['key_name' => 'welcome.custom']);
        $this->assertDatabaseHas('svp_texts', ['key_name' => 'btn.main.buy', 'locale' => 'fa']);
    }

    public function test_force_join_publish_requires_config(): void
    {
        app(SettingsStore::class)->set('force_join_channel_id', '');
        app(SettingsStore::class)->set('force_join_telegram_chat_id', 0);
        app(SettingsStore::class)->set('force_join_telegram_announce_text', '');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
            'platform' => 'telegram',
        ])->assertStatus(422)->assertJsonPath('ok', false);
    }

    public function test_telegram_proxy_test_depth(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        app(SettingsStore::class)->merge([
            'telegram_http_proxy' => 'http://proxy.test:8080',
            'telegram_bot_token' => '1:TOK',
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_proxy_test',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
