<?php

namespace Tests\Feature\Commerce;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §14 C / P5.1 — buy → approve → deliver chain (v14). */
class BuyFlowApproveDeliverTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();

        DB::table('svp_panels')->where('id', 1)->update([
            'panel_api_token' => 'tok',
            'panel_api_flavor' => 'legacy_inbound',
        ]);
        SvpPlan::query()->where('id', 1)->update([
            'inbound_id' => 1,
            'traffic_gb' => 10,
            'duration_days' => 30,
            'price' => 10000,
            'active' => 1,
        ]);

        DB::table('svp_cards')->insert([
            'owner_svp_user_id' => 0,
            'card_number' => '6037-2222-2222-2222',
            'holder_name' => 'Chain',
            'active' => 1,
            'priority' => 0,
            'created_at' => now(),
        ]);

        DB::table('svp_users')->updateOrInsert(
            ['id' => 50],
            [
                'username' => 'buyer',
                'status' => 'approved',
                'role' => 'user',
                'tg_user_id' => 12345,
                'created_at' => now(),
            ]
        );

        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        Http::fake([
            'https://panel.test/panel/api/server/getNewUUID' => Http::response(['success' => true, 'obj' => $uuid]),
            'https://panel.test/panel/api/inbounds/get/1' => Http::sequence()
                ->push(['success' => true, 'obj' => ['id' => 1, 'settings' => json_encode(['clients' => []])]])
                ->push(['success' => true, 'obj' => ['id' => 1, 'settings' => json_encode(['clients' => [[
                    'id' => $uuid, 'email' => 'u50@svp.local', 'subId' => 's1',
                ]]])]]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
            '*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
    }

    public function test_buy_approve_deliver_chain(): void
    {
        $user = SvpUser::query()->find(50);
        $buy = app(BuyHandler::class);
        $ctx = new BotContext('telegram');

        $buy->handleCallback($ctx, $user, ['parts' => ['buy', 'p', '1'], 'chat_id' => 12345]);

        $txId = (int) DB::table('svp_transactions')->where('user_id', 50)->value('id');
        $this->assertGreaterThan(0, $txId);

        $cardId = (int) DB::table('svp_cards')->where('active', 1)->value('id');
        $this->assertGreaterThan(0, $cardId);

        $buy->handleCallback($ctx, $user, [
            'parts' => ['buy', 'pm', (string) $txId, (string) $cardId],
            'chat_id' => 12345,
        ]);

        $receiptId = (int) DB::table('svp_receipts')->where('user_id', 50)->value('id');
        $this->assertGreaterThan(0, $receiptId);

        $txId = (int) DB::table('svp_receipts')->where('id', $receiptId)->value('transaction_id');
        if ($txId > 0) {
            DB::table('svp_transactions')->where('id', $txId)->update([
                'meta_json' => json_encode(['plan_id' => 1]),
                'type' => 'purchase',
            ]);
        }

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => $receiptId,
            'action' => 'approve',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_receipts', ['id' => $receiptId, 'status' => 'approved']);
        $this->assertDatabaseHas('svp_services', ['user_id' => 50, 'plan_id' => 1]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_set_status',
            'receipt_id' => $receiptId,
            'status' => 'delivered',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_receipts', ['id' => $receiptId, 'status' => 'delivered']);
    }
}
