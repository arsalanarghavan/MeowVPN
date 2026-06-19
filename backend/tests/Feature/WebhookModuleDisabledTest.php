<?php

namespace Tests\Feature;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class WebhookModuleDisabledTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->set('telegram_webhook_secret', 'test-secret');
    }

    public function test_telegram_webhook_returns_503_when_module_disabled(): void
    {
        $this->setModuleEnabled('telegram', false);

        $this->postJson('/api/v1/webhook/telegram/test-secret', ['update_id' => 1])
            ->assertStatus(503)
            ->assertJsonPath('message', 'module_missing');
    }

    public function test_reseller_webhook_returns_503_when_reseller_module_disabled(): void
    {
        $this->setModuleEnabled('reseller', false);
        app(SettingsStore::class)->set('telegram_webhook_secret', 'main-secret');

        \Illuminate\Support\Facades\DB::table('svp_users')->insert([
            'id' => 500,
            'tg_user_id' => 0,
            'status' => 'approved',
            'role' => 'reseller',
            'balance' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        \Illuminate\Support\Facades\DB::table('svp_reseller_bot_profiles')->insert([
            'reseller_svp_user_id' => 500,
            'enabled' => true,
            'webhook_secret' => \Illuminate\Support\Facades\Crypt::encryptString('reseller-wh'),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/webhook/telegram/reseller/500/reseller-wh', ['update_id' => 2])
            ->assertStatus(503)
            ->assertJsonPath('message', 'module_missing');
    }
}
