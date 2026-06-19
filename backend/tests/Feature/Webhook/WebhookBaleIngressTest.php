<?php

namespace Tests\Feature\Webhook;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WebhookBaleIngressTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $settings = app(SettingsStore::class);
        $settings->set('bale_webhook_secret', 'bale-sec');
        $settings->set('bot_enabled', true);
        $settings->set('bale_enabled', true);
    }

    public function test_bale_webhook_enqueues_update(): void
    {
        $this->postJson('/api/v1/webhook/bale/bale-sec', [
            'update_id' => 7,
            'message' => ['from' => ['id' => 1], 'chat' => ['id' => 1]],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('svp_inbound_queue', ['platform' => 'bale', 'status' => 'pending']);
    }
}
