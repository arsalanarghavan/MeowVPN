<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\AdminAlertsService;
use App\Modules\Core\Services\AdminNotifyService;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\SettingsStore;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminAlertsJobTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_panel_down_notifies_once_per_cooldown(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('enabled', true);
        $settings->set('notify_admin_panel_down', true);
        $settings->set('notify_admin_panel_down_cooldown', 30);
        $settings->set('admin_telegram_ids', [999]);

        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('runWithPanel')->andReturn(false);

        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->once();

        $this->app->instance(XuiClient::class, $xui);
        $this->app->instance(AdminNotifyService::class, $notify);

        $service = app(AdminAlertsService::class);
        $service->run();
        $service->run();
    }

    public function test_queue_backlog_alert_when_threshold_exceeded(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('enabled', true);
        $settings->set('notify_admin_panel_down', false);

        config(['svp.inbound_queue_alert_threshold' => 2]);

        foreach ([1, 2, 3] as $i) {
            DB::table('svp_inbound_queue')->insert([
                'platform' => 'telegram',
                'update_json' => '{"update_id":'.$i.'}',
                'status' => 'pending',
                'reseller_svp_user_id' => 0,
                'created_at' => now(),
            ]);
        }

        $xui = Mockery::mock(XuiClient::class);

        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->once()->with(Mockery::on(
            fn (string $msg) => str_contains($msg, 'queue backlog')
        ));

        $this->app->instance(XuiClient::class, $xui);
        $this->app->instance(AdminNotifyService::class, $notify);

        app(AdminAlertsService::class)->run();
    }
}
