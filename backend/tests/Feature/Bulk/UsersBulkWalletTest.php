<?php

namespace Tests\Feature\Bulk;

use App\Modules\Core\Services\UsersBulkWorkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class UsersBulkWalletTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bulk_wallet_enqueue_and_worker_updates_balance(): void
    {
        $before = (float) DB::table('svp_users')->where('id', 101)->value('balance');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_wallet',
            'scope' => 'custom_ids',
            'user_ids' => [101],
            'delta' => 25.5,
        ])->assertOk()->assertJsonPath('ok', true);

        $jobId = (int) DB::table('svp_users_bulk_jobs')->orderByDesc('id')->value('id');
        $this->assertDatabaseHas('svp_users_bulk_job_items', ['job_id' => $jobId, 'user_id' => 101]);

        app(UsersBulkWorkerService::class)->runBatch();

        $after = (float) DB::table('svp_users')->where('id', 101)->value('balance');
        $this->assertEqualsWithDelta($before + 25.5, $after, 0.01);

        $this->assertDatabaseHas('svp_users_bulk_job_items', [
            'job_id' => $jobId,
            'user_id' => 101,
            'status' => 'success',
        ]);
    }
}
