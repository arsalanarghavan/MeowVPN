<?php

namespace Tests\Feature\Console;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class InstallApplySettingsCommandTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    public function test_install_apply_settings_writes_urls_and_secrets(): void
    {
        $this->createSvpSchema();

        $this->artisan('svp:install-apply-settings', [
            '--core-url' => 'https://api.example.test',
            '--bale-url' => 'https://bale.example.test',
            '--telegram-webhook-secret' => 'tg-sec',
            '--enable-relay' => true,
        ])->assertSuccessful();

        $settings = app(SettingsStore::class);
        $this->assertSame('https://api.example.test', $settings->get('public_site_url'));
        $this->assertSame('https://bale.example.test', $settings->get('bale_public_webhook_base'));
        $this->assertSame('tg-sec', $settings->get('telegram_webhook_secret'));
        $this->assertTrue((bool) $settings->get('telegram_relay_enabled'));
    }
}
