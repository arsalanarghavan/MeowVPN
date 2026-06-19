<?php

namespace Tests\Feature\Bulk;

use App\Modules\Core\Services\UsersBulkWorkerService;
use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class UsersBulkVolumeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();

        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('syncService')->andReturnNull();
        $this->app->instance(XuiClient::class, $xui);
    }

    public function test_bulk_volume_worker_adds_traffic_to_service(): void
    {
        $before = (int) DB::table('svp_services')->where('id', 1)->value('total_traffic');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_volume',
            'scope' => 'custom_ids',
            'user_ids' => [101],
            'extra_gb' => 3,
        ])->assertOk()->assertJsonPath('ok', true);

        app(UsersBulkWorkerService::class)->runBatch();

        $after = (int) DB::table('svp_services')->where('id', 1)->value('total_traffic');
        $expected = $before + 3 * 1024 * 1024 * 1024;
        $this->assertSame($expected, $after);
    }
}
