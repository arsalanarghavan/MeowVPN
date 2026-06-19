<?php

namespace Tests\Feature\Xui;

use App\Modules\XuiPanel\Services\ServicePanelTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ServicePanelTransferTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_transfer_rejects_l2tp_service(): void
    {
        DB::table('svp_services')->insert([
            'user_id' => 101,
            'service_type' => 'l2tp',
            'l2tp_server_id' => 1,
            'l2tp_username' => 'u1',
            'panel_id' => 1,
            'inbound_id' => 1,
            'created_at' => now(),
        ]);
        $sid = (int) DB::table('svp_services')->max('id');

        $r = app(ServicePanelTransferService::class)->transferOne($sid, 2);
        $this->assertFalse($r['ok']);
        $this->assertSame('bad_service', $r['message'] ?? '');
    }

    public function test_transfer_batch_accepts_service_ids_payload(): void
    {
        $this->actingAsAdmin();
        $r = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_transfer',
            'service_ids' => [99999],
            'target_panel_id' => 2,
            'target_plan_id' => 1,
        ]);
        $r->assertOk();
        $this->assertArrayHasKey('data', $r->json());
        $this->assertArrayHasKey('failed', $r->json('data'));
    }
}
