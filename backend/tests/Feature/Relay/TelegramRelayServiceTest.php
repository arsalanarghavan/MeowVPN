<?php

namespace Tests\Feature\Relay;

use App\Modules\Relay\Services\TelegramRelayService;
use App\Services\SettingsStore;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class TelegramRelayServiceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_build_config_snapshot_includes_main_and_resellers(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('telegram_bot_token', 'main-token');
        $settings->set('telegram_webhook_secret', 'main-secret');
        $settings->set('telegram_relay_public_url', 'https://tg.example.test');
        $settings->set('public_site_url', 'https://panel.example.test');

        $snap = app(TelegramRelayService::class)->buildConfigSnapshot();

        $this->assertSame('https://panel.example.test', $snap['laravel_base_url']);
        $this->assertArrayNotHasKey('wp_base_url', $snap);
        $this->assertSame('main-token', $snap['main']['telegram_token']);
        $this->assertNotEmpty($snap['resellers']);
        $this->assertSame(100, $snap['resellers'][0]['reseller_svp_user_id']);
    }

    public function test_laravel_forward_url_setting_takes_precedence(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('telegram_relay_laravel_forward_url', 'https://api.example.test');
        $settings->set('public_site_url', 'https://panel.example.test');

        $snap = app(TelegramRelayService::class)->buildConfigSnapshot();

        $this->assertSame('https://api.example.test', $snap['laravel_base_url']);
    }

    public function test_collect_domains_includes_public_and_reseller_urls(): void
    {
        app(SettingsStore::class)->set('telegram_relay_public_url', 'https://tg.example.test');

        $domains = app(TelegramRelayService::class)->collectDomains();

        $this->assertContains('tg.example.test', $domains);
    }

    public function test_is_enabled_requires_flag_and_admin_url(): void
    {
        $settings = app(SettingsStore::class);
        $relay = app(TelegramRelayService::class);

        $settings->set('telegram_relay_enabled', false);
        $this->assertFalse($relay->isEnabled());

        $settings->set('telegram_relay_enabled', true);
        $settings->set('telegram_relay_admin_url', '');
        $this->assertFalse($relay->isEnabled());

        $settings->set('telegram_relay_admin_url', 'https://1.2.3.4');
        $this->assertTrue($relay->isEnabled());
    }
}
