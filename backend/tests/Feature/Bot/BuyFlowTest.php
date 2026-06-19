<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BuyFlowTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_plan_select_dispatches_deferred_checkout(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Basic',
            'price' => 100000,
            'active' => 1,
            'traffic_gb' => 0,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 200,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        \Illuminate\Support\Facades\Queue::fake();
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'cf', (string) $planId],
            'chat_id' => 200,
        ]);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Modules\Core\Bot\Jobs\DeferredPurchaseCheckoutJob::class);
    }

    public function test_deferred_checkout_sets_state_and_transaction(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Basic',
            'price' => 100000,
            'active' => 1,
            'traffic_gb' => 0,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 2001,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->sendDeferredCheckout($ctx, $user, 2001, $planId, 0);
        $this->assertSame('buy_checkout', app(BotStateService::class)->get($user->fresh()));
        $data = app(BotStateService::class)->data($user->fresh());
        $this->assertSame($planId, (int) ($data['plan_id'] ?? 0));
        $this->assertGreaterThan(0, (int) ($data['transaction_id'] ?? 0));
    }

    public function test_traffic_plan_prompts_gb_state(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Flex',
            'price' => 50000,
            'active' => 1,
            'traffic_gb' => 10,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 201,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'cf', (string) $planId],
            'chat_id' => 201,
        ]);
        $this->assertSame('buy_choose_traffic', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_c2c_payment_creates_pending_receipt(): void
    {
        DB::table('svp_cards')->insert([
            'card_number' => '6037',
            'holder_name' => 'Test',
            'active' => 1,
            'priority' => 0,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Pay',
            'price' => 20000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 202,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 20000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => $planId]),
            'created_at' => now(),
        ]);
        $cardId = (int) DB::table('svp_cards')->where('active', true)->orderBy('priority')->value('id');
        app(BotStateService::class)->set($user, 'buy_checkout', [
            'transaction_id' => $txId,
            'plan_id' => $planId,
            'amount' => 20000,
            'extra_gb' => 0,
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user->fresh(), [
            'parts' => ['buy', 'pm', (string) $txId, (string) $cardId],
            'chat_id' => 202,
        ]);
        $this->assertSame('awaiting_receipt_photo', app(BotStateService::class)->get($user->fresh()));
        $this->assertSame(1, (int) DB::table('svp_receipts')->where('status', 'pending')->count());
    }

    public function test_wallet_partial_confirm_prompts_when_balance_low(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Wallet',
            'price' => 50000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 203,
            'status' => 'approved',
            'balance' => 10000,
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 50000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => $planId]),
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'sw', (string) $txId],
            'chat_id' => 203,
        ]);
        Http::assertSentCount(1);
        $this->assertSame('pending', DB::table('svp_transactions')->where('id', $txId)->value('status'));
    }

    public function test_discount_code_applied_via_state(): void
    {
        DB::table('svp_discount_codes')->insert([
            'code' => 'OFF10',
            'discount_type' => 'percent',
            'discount_value' => 10,
            'active' => 1,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 204,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 100000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'buy_discount', ['transaction_id' => $txId]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->routeState($ctx, $user->fresh(), 204, 'OFF10');
        $this->assertSame('buy_checkout', app(BotStateService::class)->get($user->fresh()));
        $this->assertSame(90000.0, (float) DB::table('svp_transactions')->where('id', $txId)->value('amount'));
    }
}
