<?php

namespace Tests\Feature\Core;

use App\Modules\Marketing\Jobs\IdleOffersJob;
use App\Modules\Marketing\Services\IdleOffersService;
use App\Modules\XuiPanel\Jobs\InboundClientsCacheJob;
use App\Modules\XuiPanel\Jobs\PanelEconomicsRenewalJob;
use App\Modules\XuiPanel\Jobs\PanelServiceSyncJob;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\XuiPanel\Services\PanelEconomicsRenewalService;
use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

/** §12 — job-level cron handler smoke (v13). */
class CronJobDispatchTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_idle_offers_job_invokes_service(): void
    {
        $svc = Mockery::mock(IdleOffersService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(IdleOffersService::class, $svc);

        (new IdleOffersJob)->handle($svc);
    }

    public function test_inbound_clients_cache_job_runs_without_exception(): void
    {
        $configs = Mockery::mock(ConfigsSyncService::class);
        $configs->shouldReceive('syncPanelToDb')->andReturn(['ok' => true]);
        $this->app->instance(ConfigsSyncService::class, $configs);

        (new InboundClientsCacheJob)->handle($configs);
        $this->assertTrue(true);
    }

    public function test_panel_service_sync_job_runs_without_exception(): void
    {
        $xui = Mockery::mock(XuiClient::class);
        $this->app->instance(XuiClient::class, $xui);

        (new PanelServiceSyncJob)->handle($xui);
        $this->assertTrue(true);
    }

    public function test_panel_economics_renewal_job_invokes_service(): void
    {
        $svc = Mockery::mock(PanelEconomicsRenewalService::class);
        $svc->shouldReceive('run')->once();
        $this->app->instance(PanelEconomicsRenewalService::class, $svc);

        (new PanelEconomicsRenewalJob)->handle($svc);
    }
}
