<?php

namespace Tests\Feature\Core;

use App\Modules\Backup\Jobs\BackupJob;
use App\Modules\Core\Jobs\AdminAlertsJob;
use App\Modules\Core\Jobs\AutorenewJob;
use App\Modules\Core\Jobs\ExpiryJob;
use App\Modules\Core\Jobs\InboundQueueDrainJob;
use App\Modules\Core\Jobs\UsersBulkWorkerJob;
use App\Modules\Core\Services\AdminAlertsService;
use App\Modules\Core\Services\AutorenewService;
use App\Modules\Core\Services\ExpiryNotificationService;
use App\Modules\Marketing\Jobs\BroadcastWorkerJob;
use App\Modules\Marketing\Jobs\IdleOffersJob;
use App\Modules\Marketing\Jobs\MarketingJob;
use App\Modules\Marketing\Services\BroadcastWorkerService;
use App\Modules\Marketing\Services\IdleOffersService;
use App\Modules\Marketing\Services\MarketingAutomationService;
use App\Modules\XuiPanel\Jobs\InboundClientsCacheJob;
use App\Modules\XuiPanel\Jobs\PanelEconomicsRenewalJob;
use App\Modules\XuiPanel\Jobs\PanelOnlineJob;
use App\Modules\XuiPanel\Jobs\PanelServiceSyncJob;
use App\Modules\XuiPanel\Jobs\PurgeExpiredJob;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\XuiPanel\Services\PanelEconomicsRenewalService;
use App\Modules\XuiPanel\Services\PurgeExpiredService;
use App\Services\Bot\InboundQueueService;
use App\Modules\Core\Services\UsersBulkWorkerService;
use App\Support\Metrics\SvpMetrics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §12 — cron_job_duration_seconds label per scheduled svp:* job (v16). */
class CronJobMetricsTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    /** @return array<string, array{0: string, 1: callable}> */
    public static function cronJobsProvider(): array
    {
        return [
            'svp:idle_offers' => ['svp:idle_offers', function ($test) {
                $svc = Mockery::mock(IdleOffersService::class);
                $svc->shouldReceive('run')->once();
                $test->app->instance(IdleOffersService::class, $svc);
                (new IdleOffersJob)->handle($svc);
            }],
            'svp:autorenew' => ['svp:autorenew', function ($test) {
                $svc = Mockery::mock(AutorenewService::class);
                $svc->shouldReceive('run')->once();
                $test->app->instance(AutorenewService::class, $svc);
                (new AutorenewJob)->handle($svc);
            }],
            'svp:admin_alerts' => ['svp:admin_alerts', function ($test) {
                $svc = Mockery::mock(AdminAlertsService::class);
                $svc->shouldReceive('run')->once();
                $test->app->instance(AdminAlertsService::class, $svc);
                (new AdminAlertsJob)->handle($svc);
            }],
            'svp:expiry' => ['svp:expiry', function ($test) {
                $svc = Mockery::mock(ExpiryNotificationService::class);
                $svc->shouldReceive('run')->once();
                $test->app->instance(ExpiryNotificationService::class, $svc);
                (new ExpiryJob)->handle($svc);
            }],
            'svp:purge_expired' => ['svp:purge_expired', function ($test) {
                $svc = Mockery::mock(PurgeExpiredService::class);
                $svc->shouldReceive('runBatch')->once();
                $test->app->instance(PurgeExpiredService::class, $svc);
                (new PurgeExpiredJob)->handle($svc);
            }],
            'svp:broadcast' => ['svp:broadcast', function ($test) {
                $svc = Mockery::mock(BroadcastWorkerService::class);
                $svc->shouldReceive('runBatch')->once();
                $test->app->instance(BroadcastWorkerService::class, $svc);
                (new BroadcastWorkerJob)->handle($svc);
            }],
            'svp:users_bulk' => ['svp:users_bulk', function ($test) {
                $svc = Mockery::mock(UsersBulkWorkerService::class);
                $svc->shouldReceive('runBatch')->once();
                $test->app->instance(UsersBulkWorkerService::class, $svc);
                (new UsersBulkWorkerJob)->handle($svc);
            }],
            'svp:backup' => ['svp:backup', function ($test) {
                Artisan::shouldReceive('call')->once()->with('svp:backup-run');
                (new BackupJob)->handle();
            }],
            'svp:marketing' => ['svp:marketing', function ($test) {
                $svc = Mockery::mock(MarketingAutomationService::class);
                $svc->shouldReceive('runCron')->once();
                $test->app->instance(MarketingAutomationService::class, $svc);
                (new MarketingJob)->handle($svc);
            }],
            'svp:panel_economics_renewal' => ['svp:panel_economics_renewal', function ($test) {
                $svc = Mockery::mock(PanelEconomicsRenewalService::class);
                $svc->shouldReceive('run')->once();
                $test->app->instance(PanelEconomicsRenewalService::class, $svc);
                (new PanelEconomicsRenewalJob)->handle($svc);
            }],
            'svp:inbound_queue_drain' => ['svp:inbound_queue_drain', function ($test) {
                $svc = Mockery::mock(InboundQueueService::class);
                $svc->shouldReceive('drainBatch')->once();
                $test->app->instance(InboundQueueService::class, $svc);
                (new InboundQueueDrainJob)->handle($svc);
            }],
            'svp:panel_online' => ['svp:panel_online', function ($test) {
                config(['modules.modules.xui_panel.enabled' => false]);
                $test->app->forgetInstance(\App\Modules\ModuleManager::class);
                (new PanelOnlineJob)->handle(app(\App\Modules\XuiPanel\Services\XuiClient::class));
            }],
            'svp:panel_service_sync' => ['svp:panel_service_sync', function ($test) {
                config(['modules.modules.xui_panel.enabled' => false]);
                $test->app->forgetInstance(\App\Modules\ModuleManager::class);
                (new PanelServiceSyncJob)->handle(app(\App\Modules\XuiPanel\Services\XuiClient::class));
            }],
            'svp:inbound_clients_cache' => ['svp:inbound_clients_cache', function ($test) {
                config(['modules.modules.xui_panel.enabled' => false]);
                $test->app->forgetInstance(\App\Modules\ModuleManager::class);
                (new InboundClientsCacheJob)->handle(app(ConfigsSyncService::class));
            }],
        ];
    }

    /** @dataProvider cronJobsProvider */
    public function test_cron_job_records_duration_metric_label(string $label, callable $run): void
    {
        $run($this);
        $this->assertGreaterThan(0, SvpMetrics::get('cron_job_duration_seconds:'.$label));
    }
}
