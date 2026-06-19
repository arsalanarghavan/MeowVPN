<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateServicePanelDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);
    }

    public function test_service_panel_sync_and_refresh(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_sync',
            'service_id' => $svcId,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_refresh',
            'service_id' => $svcId,
        ])->assertOk();
    }

    public function test_service_set_note_and_limit_ip(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_set_note',
            'service_id' => $svcId,
            'note' => 'test note',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_set_limit_ip',
            'service_id' => $svcId,
            'limit_ip' => 2,
        ])->assertOk();
    }

    public function test_user_service_toggle_and_slots(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_service_toggle_enable',
            'service_id' => $svcId,
            'enabled' => true,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_service_add_slots',
            'service_id' => $svcId,
            'slots' => 1,
        ])->assertOk();
    }

    public function test_panel_xp_update_label(): void
    {
        $id = (int) DB::table('svp_panels')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'id' => $id,
            'label' => 'Updated Panel Label',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_panels', ['id' => $id, 'label' => 'Updated Panel Label']);
    }

    public function test_service_panel_transfer_batch_payload(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_transfer',
            'service_ids' => [99999],
            'target_panel_id' => 1,
            'target_plan_id' => 1,
        ])->assertOk()->assertJsonStructure(['data' => ['failed']]);
    }
}
