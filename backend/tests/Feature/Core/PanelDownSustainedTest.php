<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\AdminAlertsService;
use App\Modules\Core\Services\AdminNotifyService;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §18 — panel_down_alert_sustained_sec before notify (v16). */
class PanelDownSustainedTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        config(['svp.panel_down_alert_sustained_sec' => 300]);
        app(SettingsStore::class)->set('notify_admin_panel_down', true);
        app(SettingsStore::class)->set('enabled', true);
        DB::table('svp_panels')->updateOrInsert(
            ['id' => 1],
            [
                'label' => 'P1',
                'panel_url' => 'https://panel.test',
                'panel_username' => 'a',
                'panel_password' => 'b',
                'panel_api_base' => 'panel/api',
                'active' => 1,
                'sort_order' => 1,
                'created_at' => now(),
            ]
        );
    }

    protected function bindFailingPanelProbe(): void
    {
        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('runWithPanel')->andReturnUsing(function ($pid, $fn) use ($xui) {
            return $fn($xui);
        });
        $xui->shouldReceive('loginWithRetries')->andReturn(false);
        $xui->shouldReceive('probeAlertDetailLines')->andReturn(['probe failed']);
        $this->app->instance(XuiClient::class, $xui);
    }

    public function test_panel_down_does_not_notify_before_sustained_threshold(): void
    {
        $this->bindFailingPanelProbe();
        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->never();
        $this->app->instance(AdminNotifyService::class, $notify);

        app(AdminAlertsService::class)->run();
        $this->assertTrue(Cache::has('svp_admin_panel_alert_since:p1'));
    }

    public function test_panel_down_notifies_after_sustained_threshold(): void
    {
        $this->bindFailingPanelProbe();
        Cache::put('svp_admin_panel_alert_since:p1', time() - 400, 3600);

        $notifyCount = 0;
        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->andReturnUsing(function () use (&$notifyCount) {
            $notifyCount++;
        });
        $this->app->instance(AdminNotifyService::class, $notify);

        app(AdminAlertsService::class)->run();
        $this->assertSame(1, $notifyCount);
    }
}
