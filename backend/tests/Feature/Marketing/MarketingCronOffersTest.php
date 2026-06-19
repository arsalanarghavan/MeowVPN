<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Jobs\MarketingJob;
use App\Modules\Marketing\Services\MarketingAutomationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §16 P9 — marketing cron sends offers (v23). */
class MarketingCronOffersTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_marketing_cron_dispatches_offers(): void
    {
        DB::table('svp_marketing_rules')->insert([
            'segment_key' => 'never_purchased',
            'enabled' => 1,
            'message_body' => 'offer',
            'created_at' => now(),
        ]);

        $svc = Mockery::mock(MarketingAutomationService::class);
        $svc->shouldReceive('runCron')->once()->andReturn(['sent' => 3]);
        $this->app->instance(MarketingAutomationService::class, $svc);

        (new MarketingJob)->handle($svc);
    }
}
