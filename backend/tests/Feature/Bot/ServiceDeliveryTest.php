<?php

namespace Tests\Feature\Bot;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\ServiceHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ServiceDeliveryTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_service_list_shows_inline_buttons(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 300,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        SvpService::query()->create([
            'user_id' => $user->id,
            'email' => 'u@test',
            'total_traffic' => 1073741824,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->listServices($ctx, $user, 300);
        $this->assertSame(1, (int) SvpService::query()->where('user_id', $user->id)->count());
    }

    public function test_addvol_state_creates_invoice_transaction(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'VolPlan',
            'price' => 100000,
            'price_per_gb' => 5000,
            'traffic_gb' => 10,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 301,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'plan_id' => $planId,
            'email' => 'vol@test',
            'total_traffic' => 0,
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_addvol', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->routeState($ctx, $user->fresh(), 301, '2');
        $this->assertSame(1, (int) DB::table('svp_transactions')->where('type', 'service_add_volume')->where('status', 'pending')->count());
    }

    public function test_config_delivery_enqueues_job(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 303,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'cfg@test',
            'total_traffic' => 0,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        \Illuminate\Support\Facades\Queue::fake();
        app(ServiceHandler::class)->handleCallback($ctx, $user, [
            'action' => 'p',
            'svc_id' => $svcId,
            'chat_id' => 303,
        ]);
        \Illuminate\Support\Facades\Queue::assertPushed(\App\Modules\Core\Bot\Jobs\DeferredConfigDeliveryJob::class);
    }

    public function test_renew_callback_creates_invoice_transaction(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'RenewPlan',
            'price' => 50000,
            'renew_price' => 40000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 302,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svc = SvpService::query()->create([
            'user_id' => $user->id,
            'plan_id' => $planId,
            'email' => 'renew@test',
            'expires_at' => now()->subDay(),
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->handleCallback($ctx, $user, [
            'action' => 'r',
            'svc_id' => (int) $svc->id,
            'chat_id' => 302,
        ]);
        $this->assertSame(1, (int) DB::table('svp_transactions')->where('type', 'service_renew')->where('status', 'pending')->count());
    }
}
