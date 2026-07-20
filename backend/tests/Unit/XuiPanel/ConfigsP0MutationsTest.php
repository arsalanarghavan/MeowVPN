<?php

namespace Tests\Unit\XuiPanel;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Mutations\XuiPanelMutations;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ConfigsP0MutationsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_panels')->insert([
            [
                'id' => 1,
                'label' => 'XUI',
                'panel_url' => 'https://xui.test',
                'panel_username' => 'admin',
                'panel_password' => 'secret',
                'panel_api_base' => 'panel/api',
                'panel_provider' => 'xui',
                'panel_api_flavor' => 'v3_clients',
                'active' => 1,
                'created_at' => now(),
            ],
            [
                'id' => 2,
                'label' => 'PG',
                'panel_url' => 'https://pg.test',
                'panel_username' => 'admin',
                'panel_password' => 'secret',
                'panel_api_base' => 'api',
                'panel_provider' => 'pasarguard',
                'panel_api_flavor' => 'pasarguard_v5',
                'active' => 1,
                'created_at' => now(),
            ],
        ]);
    }

    public function test_del_depleted_pasarguard_not_supported(): void
    {
        $out = app(XuiPanelMutations::class)->configsPanelDelDepleted(['panel_id' => 2], null);
        $this->assertFalse($out['ok']);
        $this->assertSame('del_depleted_not_supported', $out['message']);
    }

    public function test_del_depleted_xui_calls_v3(): void
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
                    return ['success' => true, 'obj' => ['deleted' => 3]];
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

        $out = app(XuiPanelMutations::class)->configsPanelDelDepleted(['panel_id' => 1], null);
        $this->assertTrue($out['ok']);
        $this->assertSame(3, $out['data']['deleted'] ?? null);
    }

    public function test_bulk_reset_traffic_success(): void
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

        $out = app(XuiPanelMutations::class)->configsBulkResetTraffic([
            'panel_id' => 1,
            'emails' => ['a@local', 'b@local'],
        ], null);
        $this->assertTrue($out['ok']);
        $this->assertSame(2, $out['data']['succeeded'] ?? null);
    }

    public function test_client_fetch_ips_success(): void
    {
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'child@local',
            'enable' => 1,
            'client_ips_json' => '[]',
        ]);

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

                public function clientIps(string $email): array
                {
                    unset($email);

                    return ['obj' => "1.2.3.4\n5.6.7.8"];
                }

                /** @return list<string> */
                public function parseClientIpsResponse(mixed $json, int $max = 30): array
                {
                    unset($json, $max);

                    return ['1.2.3.4', '5.6.7.8'];
                }
            };

            return $callback($client);
        });

        $out = app(XuiPanelMutations::class)->configsClientFetchIps([
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'child@local',
        ], null);
        $this->assertTrue($out['ok']);
        $this->assertSame(['1.2.3.4', '5.6.7.8'], $out['data']['client_ips'] ?? null);
        $this->assertDatabaseHas('svp_panel_inbound_clients', [
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'child@local',
            'client_ips_json' => json_encode(['1.2.3.4', '5.6.7.8'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function test_client_set_inbounds_attach(): void
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

                public function isV3ClientsApi(): bool
                {
                    return true;
                }

                public function clientAttachV3(string $email, array $ids): array
                {
                    unset($email, $ids);

                    return ['success' => true];
                }

                public function responseIsSuccess(mixed $res): bool
                {
                    return is_array($res) && ! empty($res['success']);
                }
            };

            return $callback($client);
        });

        $configs = $this->mock(ConfigsSyncService::class);
        $configs->shouldReceive('syncInboundsAfterMutation')->once()->with(1, [2])->andReturnNull();

        $out = app(XuiPanelMutations::class)->configsClientSetInbounds([
            'panel_id' => 1,
            'email' => 'child@local',
            'attach_inbound_ids' => [2],
        ], null);
        $this->assertTrue($out['ok']);
    }
}
