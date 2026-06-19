<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminFinanceHandler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminFinanceBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_customer_charges_lists_transactions(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9100,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        DB::table('svp_transactions')->insert([
            'user_id' => $admin->id,
            'amount' => 25000,
            'type' => 'wallet_topup',
            'status' => 'completed',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminFinanceHandler::class)->handleCallback($ctx, ['pnl', 'fin', 'cc', '0', 'wallet_topup'], $admin, 9100);
        Http::assertSentCount(1);
    }
}
