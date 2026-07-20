<?php

namespace Tests\Unit\XuiPanel;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Mutations\XuiPanelMutations;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ConfigsMutateOpsDepthTest extends TestCase
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

    public function test_pasarguard_del_depleted_not_supported(): void
    {
        $out = app(XuiPanelMutations::class)->configsPanelDelDepleted(['panel_id' => 2], null);
        $this->assertFalse($out['ok']);
        $this->assertSame('del_depleted_not_supported', $out['message']);
    }

    public function test_bulk_reset_traffic_calls_client(): void
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
            'emails' => ['a@test', 'b@test'],
        ], null);
        $this->assertTrue($out['ok']);
        $this->assertSame(2, $out['data']['succeeded'] ?? null);
    }

    public function test_fetch_ips_pasarguard_empty(): void
    {
        $factory = $this->mock(PanelClientFactory::class);
        $factory->shouldReceive('isPasarGuardPanel')->with(2)->andReturn(true);

        $out = app(XuiPanelMutations::class)->configsClientFetchIps([
            'panel_id' => 2,
            'inbound_id' => 1,
            'email' => 'u@test',
        ], null);
        $this->assertTrue($out['ok']);
        $this->assertSame([], $out['data']['client_ips'] ?? null);
    }

    public function test_inbound_patch_not_supported_on_pasarguard(): void
    {
        $factory = $this->mock(PanelClientFactory::class);
        $factory->shouldReceive('isPasarGuardPanel')->with(2)->andReturn(true);

        $out = app(XuiPanelMutations::class)->configsInboundPatch([
            'panel_id' => 2,
            'inbound_id' => 1,
            'remark' => 'x',
        ], null);
        $this->assertFalse($out['ok']);
        $this->assertSame('inbound_patch_not_supported', $out['message']);
    }
}
