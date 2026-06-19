<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §15 — user service matrix behavioral depth */
class MutateServiceMatrixTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_user_reduce_volume_mutate(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');
        DB::table('svp_services')->where('id', $svcId)->update(['total_traffic' => 2147483648]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_reduce_volume',
            'service_id' => $svcId,
            'reduce_gb' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_user_reduce_days_mutate(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_reduce_days',
            'service_id' => $svcId,
            'days' => 3,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_service_regen_key_mutate(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_regen_key',
            'service_id' => $svcId,
        ])->assertOk();
    }

    public function test_user_service_transfer_updates_service_owner(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');
        $beforeUser = (int) DB::table('svp_services')->where('id', $svcId)->value('user_id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_service_transfer',
            'service_id' => $svcId,
            'target' => '200',
        ])->assertOk()->assertJsonPath('ok', true);

        $afterUser = (int) DB::table('svp_services')->where('id', $svcId)->value('user_id');
        $this->assertSame(200, $afterUser);
        $this->assertNotSame($beforeUser, $afterUser);
    }

    public function test_configs_clients_batch_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_clients_batch',
            'panel_id' => 1,
            'inbound_id' => 1,
            'action' => 'noop',
            'emails' => [],
        ])->assertOk();
    }
}
