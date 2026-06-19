<?php

namespace Tests\Feature\Mutate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v20 part 2 (user_service, relay, configs admin). */
class MutateDepthBatchV20Part2Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('relay', true);
        $this->setModuleEnabled('xui_panel', true);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_user_service_transfer_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_service_transfer',
            'user_id' => 101,
            'service_id' => 1,
            'target_panel_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_service_panel_sync_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_sync',
            'service_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_configs_clients_batch_admin_only(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_clients_batch',
            'panel_id' => 1,
            'action' => 'enable',
            'client_ids' => [1],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_clients_batch',
            'panel_id' => 1,
            'action' => 'enable',
            'client_ids' => [1],
        ])->assertForbidden();
    }

    public function test_telegram_relay_nginx_render_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_nginx_render',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
