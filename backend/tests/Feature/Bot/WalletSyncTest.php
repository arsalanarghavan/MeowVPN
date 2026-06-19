<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\CallbackHandler;
use App\Modules\Core\Bot\Handlers\SyncHandler;
use App\Modules\Core\Bot\Handlers\WalletHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class WalletSyncTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_wallet_history_paging_callback(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 500,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        for ($i = 0; $i < 5; $i++) {
            DB::table('svp_transactions')->insert([
                'user_id' => $user->id,
                'amount' => 1000 * ($i + 1),
                'type' => 'wallet_topup',
                'status' => 'completed',
                'created_at' => now(),
            ]);
        }
        $ctx = new BotContext('telegram');
        app(CallbackHandler::class)->handle($ctx, [
            'cb' => ['data' => 'wal:h:0', 'from' => ['id' => 500], 'message' => ['chat' => ['id' => 500]]],
            'user' => $user,
        ]);
        Http::assertSentCount(2);
    }

    public function test_sync_code_links_platform_id(): void
    {
        $source = SvpUser::query()->create([
            'tg_user_id' => 501,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $target = SvpUser::query()->create([
            'bale_user_id' => 502,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        Cache::put('svp_sync_ABC123', ['user_id' => $source->id, 'platform' => 'telegram'], 600);
        app(BotStateService::class)->set($target, 'awaiting_sync_code', []);
        $ctx = new BotContext('bale');
        app(SyncHandler::class)->handleCode($ctx, $target->fresh(), 502, 'ABC123');
        $this->assertSame(502, (int) SvpUser::query()->find($source->id)?->bale_user_id);
    }

    public function test_wallet_topup_callback_starts_state(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 503,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(WalletHandler::class)->beginTopup($ctx, $user, 503);
        $this->assertSame('wallet_topup_amount', app(BotStateService::class)->get($user->fresh()));
    }
}
