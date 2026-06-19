<?php

namespace Tests\Feature\Mutate;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateCommerceUserDepthV12Test extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);
    }

    public function test_plan_create_updates_database(): void
    {
        $res = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'name' => 'V12 Plan',
            'panel_id' => 1,
            'inbound_id' => 1,
            'traffic_gb' => 20,
            'duration_days' => 30,
            'price' => 15000,
            'active' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $planId = (int) $res->json('plan_id');
        $this->assertDatabaseHas('svp_plans', ['id' => $planId, 'name' => 'V12 Plan']);
    }

    public function test_receipt_action_approve_changes_status(): void
    {
        $receiptId = (int) DB::table('svp_receipts')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => $receiptId,
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('approved', DB::table('svp_receipts')->where('id', $receiptId)->value('status'));
    }

    public function test_user_create_service_from_plan(): void
    {
        $before = DB::table('svp_services')->count();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_create_service',
            'user_id' => 101,
            'plan_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertGreaterThan($before, DB::table('svp_services')->count());
    }

    public function test_user_add_days_extends_expiry(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');
        $before = DB::table('svp_services')->where('id', $svcId)->value('expires_at');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_add_days',
            'service_id' => $svcId,
            'days' => 5,
        ])->assertOk()->assertJsonPath('ok', true);

        $after = DB::table('svp_services')->where('id', $svcId)->value('expires_at');
        $this->assertNotSame($before, $after);
    }

    public function test_user_manual_create_status_balance_referrer_merge_bulk_jobs(): void
    {
        $create = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_manual_create',
            'username' => 'v12manual',
            'first_name' => 'V12',
            'status' => 'approved',
        ])->assertOk()->assertJsonPath('ok', true);
        $newId = (int) $create->json('user_id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_status',
            'user_id' => $newId,
            'status' => 'pending',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_balance_delta',
            'user_id' => $newId,
            'delta' => 50,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_set_referrer',
            'user_id' => $newId,
            'invited_by' => 100,
        ])->assertOk()->assertJsonPath('ok', true);

        $drop = SvpUser::query()->create([
            'username' => 'v12drop',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge_preview',
            'keep_id' => $newId,
            'drop_id' => $drop->id,
        ])->assertOk();

        $jobId = (int) DB::table('svp_users_bulk_jobs')->insertGetId([
            'operation' => 'volume',
            'scope' => 'all_approved',
            'payload_json' => json_encode(['extra_gb' => 1]),
            'status' => 'running',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_job_cancel',
            'job_id' => $jobId,
        ])->assertOk()->assertJsonPath('ok', true);
        $this->assertSame('cancelled', DB::table('svp_users_bulk_jobs')->where('id', $jobId)->value('status'));

        DB::table('svp_users_bulk_jobs')->where('id', $jobId)->update(['status' => 'cancelled']);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_job_resume',
            'job_id' => $jobId,
        ])->assertOk();
        $this->assertSame('pending', DB::table('svp_users_bulk_jobs')->where('id', $jobId)->value('status'));

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_volume',
            'extra_gb' => 2,
            'scope' => 'all_approved',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
