<?php

namespace Tests\Feature\Bot;

use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Services\MutationDispatcher;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class TelegramProxyEgressTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_telegram_proxy_test_uses_configured_proxy(): void
    {
        app(SettingsStore::class)->merge([
            'telegram_http_proxy' => 'socks5://127.0.0.1:1080',
            'telegram_bot_token' => '123:ABC',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['username' => 'bot']], 200),
        ]);

        $this->actingAsAdmin();
        $out = app(MutationDispatcher::class)->dispatch('telegram_proxy_test', [], $this->dashboardAdminUser());
        $this->assertTrue(! empty($out['result']['ok']));

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));
    }

    public function test_telegram_proxy_test_rejects_when_no_proxy(): void
    {
        app(SettingsStore::class)->set('telegram_http_proxy', '');

        $this->actingAsAdmin();
        $out = app(MutationDispatcher::class)->dispatch('telegram_proxy_test', [], $this->dashboardAdminUser());
        $this->assertFalse(! empty($out['result']['ok']));
    }

    public function test_bot_runtime_telegram_client_uses_configured_proxy(): void
    {
        app(SettingsStore::class)->merge([
            'telegram_http_proxy' => 'socks5://127.0.0.1:1080',
            'telegram_bot_token' => '123:ABC',
        ]);

        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200),
        ]);

        $runtime = app(BotRuntime::class);
        $runtime->sendMessage(new BotContext('telegram'), 1, 'hello');

        Http::assertSent(fn ($request) => str_contains($request->url(), 'api.telegram.org'));
    }
}
