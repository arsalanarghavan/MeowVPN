<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\CallbackHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminReceiptBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        DB::table('svp_settings')->insert(['key_name' => 'telegram_admin_ids', 'value' => json_encode([9001])]);
    }

    public function test_receipt_approve_callback(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 9600,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 10000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $rcId = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => 10000,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(CallbackHandler::class)->handle($ctx, [
            'cb' => [
                'data' => 'rc:a:'.$rcId,
                'from' => ['id' => 9001],
                'message' => ['chat' => ['id' => 9001], 'message_id' => 1],
                'id' => 'cb-rc',
            ],
            'user' => $admin,
        ]);
        Http::assertSentCount(2);
    }
}
