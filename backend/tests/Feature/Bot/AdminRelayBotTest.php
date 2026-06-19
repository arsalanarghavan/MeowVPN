<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminRelayHandler;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminRelayBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    protected function admin(): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => 7900,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    public function test_relay_tab_opens(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminRelayHandler::class)->openTab($ctx, 7900, $admin, 'relay');
        Http::assertSentCount(1);
    }

    public function test_relay_test_button_invokes_mutate(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminRelayHandler::class)->routeText($ctx, $admin, 7900, 'Relay test');
        Http::assertSentCount(1);
    }

    public function test_relay_set_webhook_button_invokes_mutate(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminRelayHandler::class)->routeText($ctx, $admin, 7900, 'Relay set webhook');
        Http::assertSentCount(1);
    }
}
