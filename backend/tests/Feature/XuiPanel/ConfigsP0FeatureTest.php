<?php

namespace Tests\Feature\XuiPanel;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** HTTP success paths for configs P0 mutate ops (parity with ConfigsP0MutationsTest). */
class ConfigsP0FeatureTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_configs_panel_del_depleted_success_via_http(): void
    {
        $factory = $this->mock(PanelClientFactory::class);
        $factory->shouldReceive('isPasarGuardPanel')->with(1)->andReturn(false);
        $factory->shouldReceive('runWithPanel')->once()->with(1, \Mockery::type('callable'))->andReturnUsing(function ($panelId, $callback) {
            unset($panelId);
            $client = new class {
                public function loginWithRetries(int $maxAttempts = 0, int $delayUs = 0): bool
                {
                    unset($maxAttempts, $delayUs);

                    return true;
                }

                public function isV3ClientsApi(): bool
                {
                    return true;
                }

                public function clientsDelDepletedV3(): array
                {
                    return ['success' => true, 'obj' => ['deleted' => 2]];
                }

                public function responseIsSuccess(mixed $res): bool
                {
                    return is_array($res) && ! empty($res['success']);
                }
            };

            return $callback($client);
        });

        $configs = $this->mock(ConfigsSyncService::class);
        $configs->shouldReceive('syncPanelToDb')->once()->with(1, true)->andReturn(['ok' => true]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_panel_del_depleted',
            'panel_id' => 1,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.deleted', 2);
    }

    public function test_configs_bulk_reset_traffic_success_via_http(): void
    {
        $factory = $this->mock(PanelClientFactory::class);
        $factory->shouldReceive('runWithPanel')->once()->with(1, \Mockery::type('callable'))->andReturnUsing(function ($panelId, $callback) {
            unset($panelId);
            $client = new class {
                public function loginWithRetries(int $maxAttempts = 0, int $delayUs = 0): bool
                {
                    unset($maxAttempts, $delayUs);

                    return true;
                }

                /** @param  list<string>  $emails */
                public function resetClientsTrafficBulk(array $emails): array
                {
                    return ['ok' => true, 'affected' => count($emails)];
                }
            };

            return $callback($client);
        });

        $configs = $this->mock(ConfigsSyncService::class);
        $configs->shouldReceive('syncPanelToDb')->once()->with(1, true)->andReturn(['ok' => true]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_bulk_reset_traffic',
            'panel_id' => 1,
            'emails' => ['a@local', 'b@local'],
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.succeeded', 2);
    }
}
