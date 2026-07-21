<?php

namespace Tests\Feature\Bot;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class AdminSettingsCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    protected function admin(): SvpUser
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 7800,
            'status' => 'approved',
            'role' => 'admin',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        DashboardUser::query()->create([
            'username' => 'admin_settings_'.$user->id,
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'svp_user_id' => (int) $user->id,
        ]);

        return $user;
    }

    /** @return array<string, array{0: string}> */
    public static function allowedTabsProvider(): array
    {
        return [
            'general' => ['site_settings'],
            'referral' => ['referral'],
            'whitelabel' => ['whitelabel'],
            'notifications' => ['notifications'],
            'purge_expired' => ['purge_expired'],
            'resellers_defaults' => ['resellers_defaults'],
            'proxy' => ['proxy'],
            'finance' => ['finance'],
            'plans_catalog' => ['plans_catalog'],
            'cards' => ['cards'],
            'force_join' => ['force_join'],
            'receipts' => ['receipts'],
            'backup' => ['backup'],
            'service_naming' => ['service_naming'],
            'logs' => ['logs'],
            'bots' => ['bots'],
            'bot_ui' => ['bot_ui'],
        ];
    }

    /** @return array<string, array{0: string, 1: string, 2: string, 3: string}> */
    public static function tabMutateProvider(): array
    {
        return [
            'general' => ['site_settings', 'public_site_url', 'https://parity.test', 'public_site_url'],
            'notifications' => ['notifications', 'notify_idle_after_days', '3', 'notifications.notify_idle_after_days'],
            'purge_expired' => ['purge_expired', 'purge_expired_enabled', '1', 'purge_expired_enabled'],
            'proxy' => ['proxy', 'telegram_http_proxy', 'socks5://127.0.0.1:1080', 'telegram_http_proxy'],
            'cards' => ['cards', 'display_order_hint', 'newest-first', 'cards.display_order_hint'],
            'force_join' => ['force_join', 'force_join_telegram_enabled', '1', 'force_join_telegram_enabled'],
            'whitelabel' => ['whitelabel', 'brand_name', 'BotBrand', 'brand_name'],
            'referral' => ['referral', 'referral_percent', '12', 'referral_percent'],
            'backup' => ['backup', 'backup_interval_minutes', '90', 'backup_interval_minutes'],
            'logs' => ['logs', 'logs_retention_days', '14', 'logs.logs_retention_days'],
            'plans_catalog' => ['plans_catalog', 'default_visible', 'yes', 'default_visible'],
            'resellers_defaults' => ['resellers_defaults', 'default_commission_percent', '15', 'default_commission_percent'],
            'service_naming' => ['service_naming', 'pattern', 'svc-{n}', 'pattern'],
        ];
    }

    /** @dataProvider allowedTabsProvider */
    public function test_settings_tab_opens(string $tab): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->openTab($ctx, 7800, $admin, $tab);
        Http::assertSentCount(1);
    }

    /** @dataProvider tabMutateProvider */
    public function test_settings_tab_wizard_mutates_setting(string $tab, string $field, string $value, string $settingKey): void
    {
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_settings_tab_wizard', [
            'tab' => $tab,
            'field' => $field,
        ]);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 7800, $value);
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $stored = app(SettingsStore::class)->get($settingKey);
        if ($field === 'default_visible') {
            $this->assertTrue((bool) $stored);
        } elseif (str_contains($settingKey, 'logs_retention') || in_array($field, ['purge_expired_enabled', 'force_join_enabled', 'force_join_telegram_enabled', 'backup_interval_minutes', 'referral_percent', 'default_commission_percent', 'notify_idle_after_days'], true)) {
            $this->assertSame((int) $value, (int) $stored);
        } else {
            $this->assertSame($value, (string) $stored);
        }
        Http::assertSentCount(1);
    }

    public function test_general_site_url_wizard_mutates(): void
    {
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_settings_public_site_url', ['key' => 'public_site_url']);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 7800, 'https://bot-parity.test');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame('https://bot-parity.test', (string) app(SettingsStore::class)->get('public_site_url'));
    }

    public function test_bot_ui_layout_save_mutates(): void
    {
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_wizard_bot_ui', []);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 7800, '{"rows":[]}');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertIsArray(app(SettingsStore::class)->get('bot_ui_layout'));
    }

    public function test_receipt_reject_reasons_wizard_mutates(): void
    {
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_receipt_reject_reasons', []);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 7800, 'bad photo,wrong amount');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $reasons = app(SettingsStore::class)->get('receipt_reject_reasons', []);
        $this->assertIsArray($reasons);
        $this->assertGreaterThanOrEqual(1, count($reasons));
    }

    public function test_naming_reset_mutates_service_naming_mode(): void
    {
        app(SettingsStore::class)->set('service_naming_mode', 'sequential');
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeText($ctx, $admin, 7800, 'Reset naming counter');
        $this->assertSame('legacy', (string) app(SettingsStore::class)->get('service_naming_mode'));
        Http::assertSentCount(1);
    }

    public function test_crypto_settings_wizard_from_finance_tab(): void
    {
        $this->setModuleEnabled('crypto', true);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminSettingsHandler::class);
        $handler->openTab($ctx, 7800, $admin, 'finance');
        $handler->routeText($ctx, $admin->fresh(), 7800, 'Save crypto settings');
        $handler->routeWizardText($ctx, $admin->fresh(), 7800, '{"crypto_enabled":true}');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertTrue((bool) app(SettingsStore::class)->get('crypto_enabled', false));
    }

    public function test_bot_toggle_enabled_from_bots_tab(): void
    {
        app(SettingsStore::class)->set('bot_enabled', true);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminSettingsHandler::class);
        $handler->openTab($ctx, 7800, $admin, 'bots');
        $handler->routeText($ctx, $admin->fresh(), 7800, 'Toggle bot');
        $this->assertFalse((bool) app(SettingsStore::class)->get('bot_enabled', true));
    }

    public function test_bot_admin_add_wizard_mutates_admin_ids(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminSettingsHandler::class);
        $handler->openTab($ctx, 7800, $admin, 'bots');
        $handler->routeText($ctx, $admin->fresh(), 7800, 'Add admin id');
        $handler->routeWizardText($ctx, $admin->fresh(), 7800, 'telegram');
        $handler->routeWizardText($ctx, $admin->fresh(), 7800, '88001');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $ids = app(SettingsStore::class)->get('admin_telegram_ids', []);
        $this->assertContains(88001, is_array($ids) ? $ids : []);
    }

    public function test_bot_ui_layout_reset_from_bot_ui_tab(): void
    {
        app(SettingsStore::class)->set('bot_ui_layout', ['rows' => [['a']]]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminSettingsHandler::class);
        $handler->openTab($ctx, 7800, $admin, 'bot_ui');
        $handler->routeText($ctx, $admin->fresh(), 7800, 'Reset UI layout');
        $layout = app(SettingsStore::class)->get('bot_ui_layout', null);
        $this->assertTrue($layout === null || $layout === [] || (is_array($layout) && count($layout) === 0));
    }

    public function test_telegram_proxy_test_inline_mutate(): void
    {
        app(SettingsStore::class)->set('telegram_http_proxy', 'socks5://127.0.0.1:1080');
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->openTab($ctx, 7800, $admin, 'proxy');
        app(AdminSettingsHandler::class)->routeText($ctx, $admin->fresh(), 7800, 'Test proxy');
        $this->assertGreaterThanOrEqual(2, count(Http::recorded()));
    }

    public function test_purge_expired_run_cron_inline_mutate(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->openTab($ctx, 7800, $admin, 'purge_expired');
        app(AdminSettingsHandler::class)->routeText($ctx, $admin->fresh(), 7800, 'Run purge cron');
        Http::assertSentCount(2);
    }
}
