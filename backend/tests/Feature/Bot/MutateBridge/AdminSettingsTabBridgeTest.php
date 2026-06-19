<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminSettingsTabBridgeTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    protected function admin(int $tgId = 8200): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => $tgId,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    public function test_settings_tab_wizard_calls_mutate_via_route_wizard_text(): void
    {
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_settings_tab_wizard', [
            'tab' => 'general',
            'field' => 'public_site_url',
        ]);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 8200, 'https://example.test');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame('https://example.test', (string) app(SettingsStore::class)->get('public_site_url'));
        Http::assertSentCount(1);
    }

    public function test_admin_settings_proxy_wizard_mutates(): void
    {
        $admin = $this->admin(8201);
        app(BotStateService::class)->set($admin, 'admin_settings_telegram_http_proxy', [
            'key' => 'telegram_http_proxy',
            'tab' => 'proxy',
        ]);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 8201, 'http://127.0.0.1:8080');
        $this->assertSame('http://127.0.0.1:8080', (string) app(SettingsStore::class)->get('telegram_http_proxy'));
    }

    public function test_admin_receipt_reject_reasons_wizard_mutates(): void
    {
        $admin = $this->admin(8202);
        app(BotStateService::class)->set($admin, 'admin_receipt_reject_reasons', []);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 8202, 'blurry,wrong_amount');
        $reasons = app(SettingsStore::class)->get('receipt_reject_reasons', []);
        $this->assertIsArray($reasons);
        $this->assertContains('blurry', $reasons);
    }

    public function test_admin_force_join_publish_wizard_mutates(): void
    {
        app(SettingsStore::class)->set('force_join_channel_id', '-10099');
        app(SettingsStore::class)->set('force_join_prompt', 'Join');
        $admin = $this->admin(8203);
        app(BotStateService::class)->set($admin, 'admin_force_join_publish', []);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 8203, 'Hello channel');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        Http::assertSentCount(2);
    }
}
