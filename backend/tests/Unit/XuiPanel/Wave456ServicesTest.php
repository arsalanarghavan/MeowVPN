<?php

namespace Tests\Unit\XuiPanel;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Modules\XuiPanel\Services\ServicePanelOrphanClientsService;
use App\Modules\XuiPanel\Services\PanelMergeService;
use App\Modules\XuiPanel\Services\PanelOrphanClientsService;
use App\Modules\XuiPanel\Services\PlanInboundSyncService;
use App\Modules\XuiPanel\Services\UsageLiveService;
use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class Wave456ServicesTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_xui_client_has_bulk_and_db_methods(): void
    {
        $client = app(XuiClient::class);
        $this->assertTrue(method_exists($client, 'clientsBulkAdjustV3'));
        $this->assertTrue(method_exists($client, 'getDbBinaryWithRetries'));
        $this->assertTrue(method_exists($client, 'importDbFromPath'));
        $this->assertTrue(method_exists($client, 'inboundUpdate'));
        $this->assertTrue(method_exists($client, 'clientLinksV3'));
        $this->assertTrue(method_exists($client, 'clientsDelOrphansV3'));
        $this->assertTrue(method_exists($client, 'clientsLastOnlineV3'));
        $this->assertTrue(method_exists($client, 'clientExternalLinksV3'));
        $this->assertTrue(method_exists($client, 'clientsResetAllTrafficV3'));
        $this->assertTrue(method_exists($client, 'clientExistsV3'));
        $this->assertTrue(method_exists($client, 'clientsForInboundId'));
        $this->assertTrue(method_exists($client, 'getConfigJson'));
        $this->assertTrue(method_exists($client, 'probeGetDb'));
        $this->assertTrue(method_exists($client, 'mergeClientIntoInboundSettings'));
        $this->assertTrue(method_exists($client, 'buildUpdateClientSettingsPayload'));
        $this->assertTrue(method_exists($client, 'responseIsSuccess'));
        $this->assertTrue(method_exists($client, 'getSessionStats'));
    }

    public function test_xui_client_can_fetch_panel_db_bytes(): void
    {
        $sqlite = "SQLite format 3\0".str_repeat("\0", 1024);
        Http::fake([
            'https://panel.example.com/panel/api/server/getDb' => Http::response($sqlite, 200),
        ]);

        $client = app(XuiClient::class);
        $out = $client->runWithPanel(1, function ($c) {
            return $c->getDbBinary();
        }, [
            'panel_url' => 'https://panel.example.com',
            'panel_api_base' => 'panel/api',
            'panel_api_token' => 'token-123',
        ]);

        $this->assertSame($sqlite, $out);
    }

    public function test_orphan_scan_requires_user(): void
    {
        $out = app(PanelOrphanClientsService::class)->scan(1, 0, 0);
        $this->assertFalse($out['ok']);
        $this->assertSame('bad_user', $out['message']);
    }

    public function test_service_panel_orphan_scan_filters_linked_clients(): void
    {
        DB::table('svp_services')->insert([
            [
                'user_id' => 5,
                'panel_id' => 1,
                'panel_driver' => 'xui',
                'inbound_id' => 11,
                'email' => 'u5_linked@example.com',
                'status' => 'active',
                'created_at' => now(),
            ],
        ]);

        $factory = $this->mock(PanelClientFactory::class);
        $factory->shouldReceive('loadPanelRow')->andReturn([
            'id' => 1,
            'panel_url' => 'https://panel.example.com',
            'panel_provider' => 'xui',
        ]);
        $factory->shouldReceive('runWithPanel')->andReturnUsing(function ($panelId, $callback, $panel = []) {
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

                public function clientsListPagedV3(int $page, int $size): array
                {
                    unset($size);
                    if ($page > 1) {
                        return ['clients' => []];
                    }

                    return [
                        'clients' => [
                            ['email' => 'u5_linked@example.com', 'inboundIds' => [11], 'remark' => '#5_linked'],
                            ['email' => 'u5_orphan@example.com', 'inboundIds' => [11], 'remark' => '#5_orphan'],
                        ],
                    ];
                }
            };

            return $callback($client);
        });

        $out = app(ServicePanelOrphanClientsService::class)->scan(1, 5, 0);
        $this->assertTrue($out['ok']);
        $this->assertCount(1, $out['orphans']);
        $this->assertSame('u5_orphan@example.com', $out['orphans'][0]['email']);
    }

    public function test_plan_inbound_sync_skips_unchanged(): void
    {
        $out = app(PlanInboundSyncService::class)->propagatePlanInbounds(1, [1], [1]);
        $this->assertSame('skipped', $out['mode']);
    }

    public function test_panel_merge_preview_bad_params(): void
    {
        $out = app(PanelMergeService::class)->preview(0, 1);
        $this->assertFalse($out['ok']);
        $this->assertSame('bad_params', $out['reason']);
    }

    public function test_usage_live_bad_panel(): void
    {
        $out = app(UsageLiveService::class)->refreshTrafficBatch(0, []);
        $this->assertFalse($out['ok']);
        $this->assertSame('bad_panel', $out['message']);
    }
}
