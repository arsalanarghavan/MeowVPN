<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BalePaymentTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_bale_pre_checkout_answers_ok(): void
    {
        $ctx = new BotContext('bale');
        app(BuyHandler::class)->handleBalePreCheckout($ctx, ['id' => 'pcq-1']);
        Http::assertSentCount(1);
    }

    public function test_successful_payment_sends_confirmation(): void
    {
        SvpUser::query()->create([
            'bale_user_id' => 500,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('bale');
        app(BuyHandler::class)->handleSuccessfulPayment($ctx, [
            'from' => ['id' => 500],
            'chat' => ['id' => 500],
            'successful_payment' => ['total_amount' => 1000],
        ]);
        Http::assertSentCount(1);
    }
}
