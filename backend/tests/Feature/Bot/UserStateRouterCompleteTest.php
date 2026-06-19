<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\UserStateRouter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class UserStateRouterCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_buy_state_routes_to_buy_handler(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 740,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'buy_discount', ['transaction_id' => 1]);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 740, 'INVALID');
        $this->assertTrue($handled);
    }

    public function test_service_state_routes_to_service_handler(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 741,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'router@test',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_addusers', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 741, '2');
        $this->assertTrue($handled);
    }

    public function test_awaiting_receipt_photo_routes_with_message(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 742,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'awaiting_receipt_photo', ['receipt_id' => 1]);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 742, null, [
            'photo' => [['file_id' => 'photo-1']],
        ]);
        $this->assertTrue($handled);
    }

    public function test_wallet_topup_amount_routes_to_wallet_handler(): void
    {
        DB::table('svp_cards')->insert([
            'card_number' => '6037-0000',
            'holder_name' => 'Test',
            'active' => 1,
            'priority' => 0,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 743,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'wallet_topup_amount', []);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 743, '50000');
        $this->assertTrue($handled);
        $this->assertSame('awaiting_receipt_photo', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_service_checkout_routes_via_user_state_router(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 744,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'checkout@test',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_checkout', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 744, 'INVALID');
        $this->assertTrue($handled);
    }

    public function test_service_discount_routes_via_user_state_router(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 745,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_discount', ['transaction_id' => 1]);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 745, 'CODE');
        $this->assertTrue($handled);
    }

    public function test_awaiting_receipt_text_prompts_via_user_state_router(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 746,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'awaiting_receipt_photo', ['transaction_id' => 1]);
        $ctx = new BotContext('telegram');
        $handled = app(UserStateRouter::class)->route($ctx, $user->fresh(), 746, 'not-a-photo');
        $this->assertTrue($handled);
        $this->assertSame('awaiting_receipt_photo', app(BotStateService::class)->get($user->fresh()));
        Http::assertSentCount(1);
    }
}
