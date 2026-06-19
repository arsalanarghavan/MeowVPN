<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BuyFlowCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_per_gb_plan_prompts_traffic_then_checkout(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'PerGB',
            'price' => 0,
            'pricing_type' => 'per_gb',
            'price_per_gb' => 10000,
            'traffic_gb_min' => 5,
            'traffic_gb_max' => 50,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 210,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'cf', (string) $planId],
            'chat_id' => 210,
        ]);
        $this->assertSame('buy_choose_traffic', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_discount_remove_refreshes_checkout(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Disc',
            'price' => 100000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 211,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 90000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => $planId, 'discount_code' => 'X']),
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'buy_checkout', [
            'transaction_id' => $txId,
            'plan_id' => $planId,
            'amount' => 90000,
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user->fresh(), [
            'parts' => ['buy', 'dd', (string) $txId],
            'chat_id' => 211,
        ]);
        $this->assertSame('buy_checkout', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_c2c_payment_defers_invoice_job(): void
    {
        Queue::fake();
        DB::table('svp_cards')->insert([
            'card_number' => '6037',
            'holder_name' => 'Test',
            'active' => 1,
            'priority' => 0,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 212,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 50000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $cardId = (int) DB::table('svp_cards')->value('id');
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'pm', (string) $txId, (string) $cardId],
            'chat_id' => 212,
            'callback_query_id' => 'cb-1',
        ]);
        Queue::assertPushed(\App\Modules\Core\Bot\Jobs\DeferredC2cInvoiceJob::class);
    }
}
