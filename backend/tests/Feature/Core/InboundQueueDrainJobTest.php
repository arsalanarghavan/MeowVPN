<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Jobs\InboundQueueDrainJob;
use App\Services\Bot\InboundQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class InboundQueueDrainJobTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_drain_job_invokes_service(): void
    {
        DB::table('svp_inbound_queue')->insert([
            'platform' => 'telegram',
            'update_json' => json_encode(['update_id' => 1]),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $svc = Mockery::mock(InboundQueueService::class);
        $svc->shouldReceive('drainBatch')->once()->andReturn(1);
        $this->app->instance(InboundQueueService::class, $svc);

        (new InboundQueueDrainJob)->handle($svc);
    }
}
