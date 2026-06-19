<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BaleTopupCreditTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_successful_payment_credits_wallet_topup_balance(): void
    {
        $user = SvpUser::query()->create([
            'bale_user_id' => 760,
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 30000,
            'type' => 'wallet_topup',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('bale');
        app(BuyHandler::class)->handleSuccessfulPayment($ctx, [
            'from' => ['id' => 760],
            'chat' => ['id' => 760],
            'successful_payment' => [
                'invoice_payload' => 'tx:'.$txId,
                'total_amount' => 30000,
            ],
        ]);
        $this->assertSame(30000.0, (float) SvpUser::query()->find($user->id)?->balance);
        Http::assertSentCount(1);
    }
}
