<?php

namespace Tests\Feature\Core;

use App\Services\SettingsStore;
use App\Support\Metrics\SvpMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class MetricsWebhookTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->merge([
            'telegram_webhook_secret' => 'm-sec',
            'bot_enabled' => true,
            'telegram_enabled' => true,
        ]);
    }

    public function test_webhook_increments_received_total_metric(): void
    {
        $before = SvpMetrics::get('webhook_received_total');

        $this->postJson('/api/v1/webhook/telegram/m-sec', ['update_id' => 99])
            ->assertOk();

        $this->assertGreaterThan($before, SvpMetrics::get('webhook_received_total'));
    }
}
