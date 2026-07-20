<?php

namespace Tests\Unit\XuiPanel;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Mutations\XuiPanelMutations;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ConfigsPanelDelOrphansTest extends TestCase
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

    public function test_pasarguard_panel_returns_not_supported(): void
    {
        $out = app(XuiPanelMutations::class)->configsPanelDelOrphans(['panel_id' => 2], null);
        $this->assertFalse($out['ok']);
        $this->assertSame('del_orphans_not_supported', $out['message']);
    }

    public function test_xui_panel_calls_clients_del_orphans_v3(): void
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

                public function clientsDelOrphansV3(): array
                {
                    return ['success' => true, 'obj' => ['deleted' => 4]];
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

        $out = app(XuiPanelMutations::class)->configsPanelDelOrphans(['panel_id' => 1], null);
        $this->assertTrue($out['ok']);
        $this->assertSame(4, $out['data']['deleted'] ?? null);
    }
}
