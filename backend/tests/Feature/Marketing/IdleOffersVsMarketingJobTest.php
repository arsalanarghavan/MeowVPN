<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Jobs\IdleOffersJob;
use App\Modules\Marketing\Jobs\MarketingJob;
use App\Modules\Marketing\Services\IdleOffersService;
use App\Modules\Marketing\Services\MarketingAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** IdleOffersJob vs MarketingJob invoke different services (v14). */
class IdleOffersVsMarketingJobTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    public function test_idle_offers_job_uses_idle_service(): void
    {
        $idle = Mockery::mock(IdleOffersService::class);
        $idle->shouldReceive('run')->once();
        $this->app->instance(IdleOffersService::class, $idle);

        $marketing = Mockery::mock(MarketingAutomationService::class);
        $marketing->shouldNotReceive('runCron');
        $this->app->instance(MarketingAutomationService::class, $marketing);

        (new IdleOffersJob)->handle($idle);
    }

    public function test_marketing_job_uses_automation_service(): void
    {
        $marketing = Mockery::mock(MarketingAutomationService::class);
        $marketing->shouldReceive('runCron')->once()->andReturn([]);
        $this->app->instance(MarketingAutomationService::class, $marketing);

        $idle = Mockery::mock(IdleOffersService::class);
        $idle->shouldNotReceive('run');
        $this->app->instance(IdleOffersService::class, $idle);

        (new MarketingJob)->handle($marketing);
    }
}
