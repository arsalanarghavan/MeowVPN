<?php

namespace Tests\Feature;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Services\Commerce\ReceiptProcessorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ReceiptApproveDeliveryTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        DB::table('svp_panels')->where('id', 1)->update([
            'panel_api_token' => 'tok',
            'panel_api_flavor' => 'legacy_inbound',
        ]);
        SvpPlan::query()->where('id', 1)->update(['inbound_id' => 1, 'traffic_gb' => 10, 'duration_days' => 30]);
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        Http::fake([
            'https://panel.test/panel/api/server/getNewUUID' => Http::response(['success' => true, 'obj' => $uuid]),
            'https://panel.test/panel/api/inbounds/get/1' => Http::sequence()
                ->push(['success' => true, 'obj' => ['id' => 1, 'settings' => json_encode(['clients' => []])]])
                ->push(['success' => true, 'obj' => ['id' => 1, 'settings' => json_encode(['clients' => [[
                    'id' => $uuid, 'email' => 'u101@svp.local', 'subId' => 's1',
                ]]])]]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
            '*' => Http::response(['ok' => true], 200),
        ]);
    }

    public function test_approve_creates_service_for_purchase(): void
    {
        SvpPlan::query()->where('id', 1)->update(['duration_days' => 30, 'traffic_gb' => 10]);
        $user = SvpUser::query()->find(101);
        $user->tg_user_id = 555101;
        $user->save();

        $txId = DB::table('svp_transactions')->insertGetId([
            'user_id' => 101,
            'amount' => 10000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => 1]),
            'created_at' => now(),
        ]);

        $receiptId = DB::table('svp_receipts')->insertGetId([
            'user_id' => 101,
            'transaction_id' => $txId,
            'amount' => 10000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $result = app(ReceiptProcessorService::class)->approve($receiptId, 'test-admin');
        $this->assertTrue($result['ok']);

        $this->assertDatabaseHas('svp_services', ['user_id' => 101, 'plan_id' => 1]);
        $this->assertDatabaseHas('svp_receipts', ['id' => $receiptId, 'status' => 'approved']);
    }

    public function test_mutate_receipt_action_approve(): void
    {
        $this->actingAsAdmin();
        SvpPlan::query()->where('id', 1)->update(['duration_days' => 30]);

        $txId = DB::table('svp_transactions')->insertGetId([
            'user_id' => 101,
            'amount' => 5000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => 1]),
            'created_at' => now(),
        ]);

        DB::table('svp_receipts')->where('id', 1)->update([
            'transaction_id' => $txId,
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => 1,
            'action' => 'approve',
        ])->assertOk();

        $this->assertDatabaseHas('svp_services', ['user_id' => 101]);
    }
}
