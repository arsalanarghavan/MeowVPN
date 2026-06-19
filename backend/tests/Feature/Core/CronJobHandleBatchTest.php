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
use App\Modules\Core\Services\UsersBulkWorkerService;
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
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\Bot\InboundQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** Job::handle() smoke for all 14 scheduled svp:* cron jobs (v17). */
class CronJobHandleBatchTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_backup_job_invokes_artisan_command(): void
    {
        Artisan::shouldReceive('call')->once()->with('svp:backup-run');
        (new BackupJob)->handle();
        $this->addToAssertionCount(1);
    }

    public function test_purge_expired_job_invokes_service(): void
    {
        $svc = Mockery::mock(PurgeExpiredService::class);
        $svc->shouldReceive('runBatch')->once();
        $this->app->instance(PurgeExpiredService::class, $svc);
        (new PurgeExpiredJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_broadcast_worker_job_invokes_service(): void
    {
        $svc = Mockery::mock(BroadcastWorkerService::class);
        $svc->shouldReceive('runBatch')->once();
        $this->app->instance(BroadcastWorkerService::class, $svc);
        (new BroadcastWorkerJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_users_bulk_worker_job_invokes_service(): void
    {
        $svc = Mockery::mock(UsersBulkWorkerService::class);
        $svc->shouldReceive('runBatch')->once();
        $this->app->instance(UsersBulkWorkerService::class, $svc);
        (new UsersBulkWorkerJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_panel_online_job_invokes_xui_client(): void
    {
        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('runWithPanel')->andReturn(0);
        $this->app->instance(XuiClient::class, $xui);
        (new PanelOnlineJob)->handle($xui);
        $this->addToAssertionCount(1);
    }

    public function test_panel_service_sync_job_invokes_xui_client(): void
    {
        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('runWithPanel')->andReturn(null);
        $this->app->instance(XuiClient::class, $xui);
        (new PanelServiceSyncJob)->handle($xui);
        $this->addToAssertionCount(1);
    }

    public function test_inbound_clients_cache_job_invokes_configs_sync(): void
    {
        $svc = Mockery::mock(ConfigsSyncService::class);
        $svc->shouldReceive('syncPanelToDb')->andReturn([]);
        $this->app->instance(ConfigsSyncService::class, $svc);
        (new InboundClientsCacheJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_expiry_job_invokes_service(): void
    {
        $svc = Mockery::mock(ExpiryNotificationService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(ExpiryNotificationService::class, $svc);
        (new ExpiryJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_autorenew_job_invokes_service(): void
    {
        $svc = Mockery::mock(AutorenewService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(AutorenewService::class, $svc);
        (new AutorenewJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_idle_offers_job_invokes_service(): void
    {
        $svc = Mockery::mock(IdleOffersService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(IdleOffersService::class, $svc);
        (new IdleOffersJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_marketing_job_invokes_service(): void
    {
        $svc = Mockery::mock(MarketingAutomationService::class);
        $svc->shouldReceive('runCron')->once();
        $this->app->instance(MarketingAutomationService::class, $svc);
        (new MarketingJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_admin_alerts_job_invokes_service(): void
    {
        $svc = Mockery::mock(AdminAlertsService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(AdminAlertsService::class, $svc);
        (new AdminAlertsJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_panel_economics_renewal_job_invokes_service(): void
    {
        $svc = Mockery::mock(PanelEconomicsRenewalService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(PanelEconomicsRenewalService::class, $svc);
        (new PanelEconomicsRenewalJob)->handle($svc);
        $this->addToAssertionCount(1);
    }

    public function test_inbound_queue_drain_job_invokes_service(): void
    {
        $svc = Mockery::mock(InboundQueueService::class);
        $svc->shouldReceive('drainBatch')->once();
        $this->app->instance(InboundQueueService::class, $svc);
        (new InboundQueueDrainJob)->handle($svc);
        $this->addToAssertionCount(1);
    }
}
