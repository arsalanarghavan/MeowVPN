<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateServiceUserDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);
    }

    public function test_service_delete_soft_deletes(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_delete',
            'service_id' => $svcId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertNotNull(DB::table('svp_services')->where('id', $svcId)->value('deleted_at'));
    }

    public function test_service_alerts_patch(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_alerts_patch',
            'service_id' => $svcId,
            'alerts' => ['traffic' => true, 'expiry' => false],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_service_regen_sub_id(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_regen_sub_id',
            'service_id' => $svcId,
        ])->assertOk();
    }

    public function test_user_renew_service_and_add_volume(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_renew_service',
            'service_id' => $svcId,
            'mode' => 'free',
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_add_volume',
            'service_id' => $svcId,
            'extra_gb' => 2,
        ])->assertOk();
    }

    public function test_user_service_reduce_slots_and_set_role(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_service_reduce_slots',
            'service_id' => $svcId,
            'slots' => 0,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_set_role',
            'user_id' => 101,
            'role' => 'user',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_user_admin_message(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_admin_message',
            'user_id' => 101,
            'message' => 'Hello from admin',
            'channel' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_service_apply_canonical_panel_identity_and_delete_client(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_apply_canonical_panel_identity',
            'service_id' => $svcId,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_delete_client',
            'service_id' => $svcId,
        ])->assertOk();
    }
}
