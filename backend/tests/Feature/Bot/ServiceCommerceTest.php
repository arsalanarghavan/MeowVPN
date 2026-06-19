<?php

namespace Tests\Feature\Bot;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotServiceCheckoutService;
use App\Services\Commerce\TransactionFulfillService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ServiceCommerceTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_renew_creates_priced_transaction(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'RenewPlan',
            'price' => 50000,
            'renew_price' => 40000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 601,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svc = SvpService::query()->create([
            'user_id' => $user->id,
            'plan_id' => $planId,
            'email' => 'renew@test',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        $result = app(BotServiceCheckoutService::class)->beginRenew($user, $ctx, (int) $svc->id);
        $this->assertTrue(! empty($result['ok']));
        $this->assertGreaterThan(0, (int) ($result['transaction_id'] ?? 0));
        $this->assertSame(40000.0, (float) DB::table('svp_transactions')->where('id', (int) $result['transaction_id'])->value('amount'));
    }

    public function test_fulfill_service_renew_extends_expiry(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 602,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svc = SvpService::query()->create([
            'user_id' => $user->id,
            'email' => 'fulfill@test',
            'expires_at' => now()->subDay(),
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'service_id' => $svc->id,
            'amount' => 1000,
            'type' => 'service_renew',
            'status' => 'pending',
            'meta_json' => json_encode(['service_id' => $svc->id]),
            'created_at' => now(),
        ]);
        $result = app(TransactionFulfillService::class)->fulfillByTransaction($txId, 'wallet');
        $this->assertTrue($result['ok']);
        $fresh = SvpService::query()->find($svc->id);
        $this->assertTrue($fresh?->expires_at?->isFuture() ?? false);
    }

    public function test_wallet_topup_fulfill_credits_balance(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 603,
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 25000,
            'type' => 'wallet_topup',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $result = app(TransactionFulfillService::class)->fulfillByTransaction($txId, 'bale_wallet');
        $this->assertTrue($result['ok']);
        $this->assertSame(25000.0, (float) SvpUser::query()->find($user->id)?->balance);
    }
}
