<?php

namespace Tests\Feature\Relay;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §14 B.4 — relay setup order + sync/webhook (v23). */
class RelaySetupOrderTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('relay', true);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_relay_sync_then_set_webhook_order(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', ['op' => 'telegram_relay_sync'])
            ->assertOk()->assertJsonPath('ok', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', ['op' => 'telegram_relay_set_webhook'])
            ->assertOk()->assertJsonPath('ok', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', ['op' => 'telegram_relay_admin_doctor'])
            ->assertOk()->assertJsonPath('ok', true);
    }
}
