<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Jobs\DeferredPurchaseCheckoutJob;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BuyFlowEdgeCasesTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_buy_g_all_shows_plan_picker(): void
    {
        DB::table('svp_plans')->insert([
            'name' => 'CatPlan',
            'price' => 50000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 750,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'g', 'all'],
            'chat_id' => 750,
        ]);
        Http::assertSentCount(1);
    }

    public function test_buy_discount_fail_keeps_buy_checkout(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Edge',
            'price' => 100000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 751,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 100000,
            'type' => 'purchase',
            'status' => 'pending',
            'meta_json' => json_encode(['plan_id' => $planId]),
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'buy_discount', ['transaction_id' => $txId]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->routeState($ctx, $user->fresh(), 751, 'BADCODE');
        $this->assertSame('buy_checkout', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_plan_select_dispatches_deferred_purchase_checkout_job(): void
    {
        Queue::fake();
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Deferred',
            'price' => 80000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 752,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->handleCallback($ctx, $user, [
            'parts' => ['buy', 'cf', (string) $planId],
            'chat_id' => 752,
        ]);
        Queue::assertPushed(DeferredPurchaseCheckoutJob::class);
    }

    public function test_buy_choose_clients_writes_clients_count_to_deferred_cache(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'MultiClient',
            'price' => 90000,
            'clients_count' => 2,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 753,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'buy_choose_clients', ['plan_id' => $planId, 'extra_gb' => 0]);
        $ctx = new BotContext('telegram');
        app(BuyHandler::class)->routeState($ctx, $user->fresh(), 753, '3');
        $payload = Cache::get('bot_deferred_checkout:'.$user->id);
        $this->assertIsArray($payload);
        $this->assertSame(3, (int) ($payload['clients_count'] ?? 0));
        $this->assertSame('', app(BotStateService::class)->get($user->fresh()));
    }
}
