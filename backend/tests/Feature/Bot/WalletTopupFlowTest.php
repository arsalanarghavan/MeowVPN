<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\WalletHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WalletTopupFlowTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_topup_state_creates_pending_transaction(): void
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
            'tg_user_id' => 100,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'wallet_topup_amount', []);
        $ctx = new BotContext('telegram');
        app(WalletHandler::class)->handleTopupState($ctx, $user->fresh(), 100, '50000');
        $this->assertSame('awaiting_receipt_photo', app(BotStateService::class)->get($user->fresh()));
        $this->assertSame(1, (int) DB::table('svp_transactions')->where('type', 'wallet_topup')->count());
    }

    public function test_topup_invalid_amount_keeps_state(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 101,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'wallet_topup_amount', []);
        $ctx = new BotContext('telegram');
        app(WalletHandler::class)->handleTopupState($ctx, $user->fresh(), 101, 'abc');
        $this->assertSame('wallet_topup_amount', app(BotStateService::class)->get($user->fresh()));
    }
}
