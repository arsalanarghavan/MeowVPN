<?php

namespace Tests\Feature\Bulk;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 C.3 — bulk cancel/resume */
class UsersBulkCancelResumeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bulk_job_cancel_and_resume(): void
    {
        $this->actingAsAdmin();
        $jobId = DB::table('svp_users_bulk_jobs')->insertGetId([
            'actor_dashboard_user_id' => 1,
            'op' => 'wallet',
            'status' => 'running',
            'total_items' => 1,
            'done_items' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('svp_users_bulk_job_items')->insert([
            'job_id' => $jobId,
            'svp_user_id' => 101,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_job_cancel',
            'job_id' => $jobId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('cancelled', DB::table('svp_users_bulk_jobs')->where('id', $jobId)->value('status'));

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_job_resume',
            'job_id' => $jobId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('pending', DB::table('svp_users_bulk_jobs')->where('id', $jobId)->value('status'));
    }
}
