<?php

namespace Tests\Feature\Reseller;

use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ResellerWebhookTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_invalid_reseller_secret_returns_403(): void
    {
        $this->postJson('/api/v1/webhook/telegram/reseller/100/wrong-secret', [
            'update_id' => 1,
        ])->assertForbidden();
    }

    public function test_valid_reseller_webhook_enqueues_with_reseller_id(): void
    {
        $this->postJson('/api/v1/webhook/telegram/reseller/100/test-reseller-webhook-secret', [
            'update_id' => 99,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 1, 'first_name' => 'R'],
                'chat' => ['id' => 1, 'type' => 'private'],
                'text' => '/start',
            ],
        ])->assertOk()->assertJson(['ok' => true]);

        $this->assertDatabaseHas('svp_inbound_queue', [
            'platform' => 'telegram',
            'reseller_svp_user_id' => 100,
            'status' => 'pending',
        ]);
    }
}
