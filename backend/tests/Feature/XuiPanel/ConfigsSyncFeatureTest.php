<?php

namespace Tests\Feature\XuiPanel;

use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ConfigsSyncFeatureTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_configs_sync_rejects_missing_panel(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/configs-sync', [
            'panel_id' => 99999,
        ])->assertStatus(400)->assertJsonPath('message', 'panel_not_found');
    }

    public function test_configs_sync_happy_path_with_mocked_xui(): void
    {
        Cache::flush();
        $this->mock(XuiClient::class, function ($mock): void {
            $mock->shouldReceive('runWithPanel')
                ->once()
                ->with(1, \Mockery::type('callable'))
                ->andReturnUsing(function (int $panelId, callable $cb) {
                    return ['ok' => true, 'data' => ['synced_inbounds' => 1, 'rows' => 3, 'truncated' => false]];
                });
        });

        $this->actingAsAdmin()->postJson('/api/v1/admin/configs-sync', [
            'panel_id' => 1,
            'force' => true,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('data.rows', 3);
    }

    public function test_configs_sync_service_skips_when_recent_without_force(): void
    {
        Cache::flush();
        Cache::put('svp_cfgsync_done_1', time(), 86400);

        $result = app(ConfigsSyncService::class)->syncPanelToDb(1, false);

        $this->assertTrue($result['ok']);
        $this->assertTrue($result['data']['skipped'] ?? false);
        $this->assertSame('recent', $result['data']['reason'] ?? '');
    }
}
