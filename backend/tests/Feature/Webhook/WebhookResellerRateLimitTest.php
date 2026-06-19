<?php

namespace Tests\Feature\Webhook;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §13 — reseller webhook rate 60/min (v13). */
class WebhookResellerRateLimitTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        config(['svp.webhook_reseller_rate_limit_per_min' => 2]);

        $settings = app(SettingsStore::class);
        $settings->set('bot_enabled', true);
        $settings->set('telegram_enabled', true);

        DB::table('svp_users')->insert([
            'id' => 500,
            'username' => 'rw',
            'role' => 'reseller',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_reseller_bot_profiles')->insert([
            'reseller_svp_user_id' => 500,
            'enabled' => true,
            'webhook_secret' => Crypt::encryptString('reseller-wh'),
            'updated_at' => now(),
        ]);
    }

    public function test_reseller_webhook_rate_limit_returns_429(): void
    {
        $payload = ['update_id' => 1, 'message' => ['from' => ['id' => 1], 'chat' => ['id' => 1]]];
        $url = '/api/v1/webhook/telegram/reseller/500/reseller-wh';

        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)->assertOk();
        $this->postJson($url, $payload)
            ->assertStatus(429)
            ->assertJsonPath('message', 'rate_limited');
    }
}
