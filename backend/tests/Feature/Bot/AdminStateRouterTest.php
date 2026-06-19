<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminStateRouter;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminStateRouterTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_user_state_router_buy_takes_precedence_over_admin(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 9800,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'buy_discount', ['transaction_id' => 1]);
        $ctx = new BotContext('telegram');
        $handled = app(\App\Modules\Core\Bot\Services\UserStateRouter::class)->route($ctx, $user->fresh(), 9800, 'CODE');
        $this->assertTrue($handled);
    }

    public function test_admin_finance_state_routes_amount(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9801,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($admin, 'admin_finance_reseller_charge', ['step' => 'amount']);
        $ctx = new BotContext('telegram');
        $handled = app(AdminStateRouter::class)->route($ctx, $admin->fresh(), 9801, '50000', [], ['id' => 9801]);
        $this->assertTrue($handled);
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
    }
}
