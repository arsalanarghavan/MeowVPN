<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\UiReplyRouter;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class UserMenuFlowTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_buy_button_routes_to_plan_picker(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 400,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        $buyLabel = app(TextService::class)->getForUser('btn.main.buy', $user, 'Buy');
        $handled = app(UiReplyRouter::class)->routeMainMenuText($ctx, $user, 400, $buyLabel);
        $this->assertTrue($handled);
    }

    public function test_wallet_button_routes(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 401,
            'status' => 'approved',
            'balance' => 5000,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        $label = app(TextService::class)->getForUser('btn.main.wallet', $user, 'Wallet');
        $handled = app(UiReplyRouter::class)->routeMainMenuText($ctx, $user, 401, $label);
        $this->assertTrue($handled);
    }
}
