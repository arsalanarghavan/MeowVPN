<?php

namespace Tests\Feature;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\UpdateRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BuyFlowFeatureTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true], 200)]);

        DB::table('svp_plans')->insert([
            'id' => 1,
            'name' => 'Plan 1',
            'category' => 'normal',
            'panel_id' => 1,
            'service_type' => 'xray',
            'active' => 1,
            'price' => 10000,
            'duration_days' => 30,
            'created_at' => now(),
        ]);

        DB::table('svp_cards')->insert([
            'owner_svp_user_id' => 0,
            'card_number' => '6037-1111-1111-1111',
            'holder_name' => 'Test',
            'active' => 1,
            'priority' => 0,
            'created_at' => now(),
        ]);

        SvpUser::query()->create([
            'id' => 50,
            'tg_user_id' => 12345,
            'username' => 'buyer',
            'status' => 'approved',
            'role' => 'user',
            'created_at' => now(),
        ]);
    }

    public function test_buy_callback_creates_pending_receipt(): void
    {
        $user = SvpUser::query()->find(50);
        $ctx = new BotContext('telegram');
        $buy = app(BuyHandler::class);

        $buy->handleCallback($ctx, $user, [
            'parts' => ['buy', 'p', '1'],
            'chat_id' => 12345,
        ]);

        $buy->handleCallback($ctx, $user, [
            'parts' => ['buy', 'pm', 'c2c'],
            'chat_id' => 12345,
        ]);

        $this->assertDatabaseHas('svp_receipts', ['user_id' => 50, 'status' => 'pending']);
        $this->assertDatabaseHas('svp_transactions', ['user_id' => 50, 'type' => 'purchase']);
    }

    public function test_start_update_routes_without_exception(): void
    {
        $router = app(UpdateRouter::class);
        $ctx = new BotContext('telegram');

        $router->dispatch($ctx, [
            'update_id' => 1,
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 12345, 'first_name' => 'Buyer'],
                'chat' => ['id' => 12345, 'type' => 'private'],
                'text' => '/start',
            ],
        ]);

        $this->assertDatabaseHas('svp_users', ['tg_user_id' => 12345]);
    }
}
