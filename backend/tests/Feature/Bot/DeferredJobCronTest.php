<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\BuyHandler;
use App\Modules\Core\Bot\Jobs\DeferredC2cSweepJob;
use App\Modules\Core\Bot\Jobs\DeferredCheckoutSweepJob;
use App\Modules\Core\Bot\Jobs\DeferredConfigDeliveryCronJob;
use App\Modules\Core\Bot\Services\BotConfigDeliveryService;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class DeferredJobCronTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        if (! Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        config(['cache.default' => 'database', 'cache.prefix' => '']);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_sweep_job_processes_bot_deferred_checkout_cache_keys(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'SweepPlan',
            'price' => 60000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 810,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        Cache::put('bot_deferred_checkout:'.$user->id, [
            'user_id' => $user->id,
            'chat_id' => 810,
            'platform' => 'telegram',
            'plan_id' => $planId,
            'extra_gb' => 0,
            'clients_count' => 1,
        ], 600);
        app(DeferredCheckoutSweepJob::class)->handle(app(BuyHandler::class));
        $this->assertSame('buy_checkout', app(BotStateService::class)->get($user->fresh()));
        Http::assertSentCount(1);
    }

    public function test_c2c_sweep_job_processes_deferred_c2c_cache(): void
    {
        DB::table('svp_cards')->insert([
            'card_number' => '6037-1111',
            'holder_name' => 'Sweep',
            'active' => 1,
            'priority' => 0,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 811,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 50000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        Cache::put('bot_deferred_c2c:'.$user->id, [
            'user_id' => $user->id,
            'chat_id' => 811,
            'platform' => 'telegram',
            'transaction_id' => $txId,
            'card_id' => 1,
        ], 600);
        app(DeferredC2cSweepJob::class)->handle(app(BuyHandler::class), app(BotStateService::class));
        $this->assertSame('awaiting_receipt_photo', app(BotStateService::class)->get($user->fresh()));
        Http::assertSentCount(1);
    }

    public function test_config_delivery_cron_delivers_cached_payload(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 812,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'CronPanel',
            'subscription_public_base' => 'https://sub.example.com',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'cron-cfg@test',
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'sub_id' => 'cron-sub',
            'xui_client_uuid' => 'uuid-cron',
            'created_at' => now(),
        ]);
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'email' => 'cron-cfg@test',
            'client_json' => json_encode(['vless' => 'vless://uuid@host:443?type=tcp#cron']),
        ]);
        $key = "bot_config_delivery:{$user->id}:{$svcId}:telegram:config";
        Cache::put($key, [
            'platform' => 'telegram',
            'chat_id' => 812,
            'service_id' => $svcId,
            'mode' => 'config',
            'user_id' => $user->id,
            'owner_id' => $user->id,
            'cb_id' => 'cb-cron-1',
            'attempt' => 0,
            'cache_key' => $key,
        ], 600);
        app(DeferredConfigDeliveryCronJob::class)->handle(app(BotConfigDeliveryService::class));
        $this->assertNull(Cache::get($key));
        Http::assertSent(function ($request) {
            return str_contains((string) $request->url(), 'sendMessage')
                || str_contains((string) $request->url(), 'sendPhoto')
                || str_contains((string) $request->url(), 'answerCallbackQuery');
        });
    }
}
