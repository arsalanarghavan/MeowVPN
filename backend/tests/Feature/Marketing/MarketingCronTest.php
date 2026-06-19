<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Jobs\MarketingJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MarketingCronTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);
    }

    public function test_marketing_job_dispatches_offers(): void
    {
        (new MarketingJob)->handle(app(\App\Modules\Marketing\Services\MarketingAutomationService::class));

        $this->assertGreaterThan(0, DB::table('svp_marketing_offers')->where('status', 'sent')->count());
    }
}
