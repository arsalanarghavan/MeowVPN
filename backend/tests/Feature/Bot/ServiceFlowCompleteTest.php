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

class ServiceFlowCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_list_paging_callback(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 400,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        for ($i = 0; $i < 12; $i++) {
            SvpService::query()->create([
                'user_id' => $user->id,
                'email' => "svc{$i}@test",
                'created_at' => now(),
            ]);
        }
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->handleCallback($ctx, $user, [
            'action' => 'lp',
            'svc_id' => 10,
            'chat_id' => 400,
        ]);
        Http::assertSentCount(1);
    }

    public function test_addusers_creates_invoice(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'SlotsPlan',
            'price' => 30000,
            'clients_count' => 3,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $user = SvpUser::query()->create([
            'tg_user_id' => 401,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'plan_id' => $planId,
            'email' => 'slots@test',
            'client_slots' => 1,
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_addusers', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->routeState($ctx, $user->fresh(), 401, '3');
        $this->assertSame(1, (int) DB::table('svp_transactions')->where('type', 'service_add_slots')->where('status', 'pending')->count());
    }

    public function test_alerts_wizard_three_steps(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 402,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        app(\App\Services\SettingsStore::class)->set('telegram_admin_ids', [402]);
        $svcId = DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'alerts@test',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_alerts_pct', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        $handler = app(ServiceHandler::class);
        $handler->routeState($ctx, $user->fresh(), 402, '80');
        $this->assertSame('service_alerts_exp', app(BotStateService::class)->get($user->fresh()));
        $handler->routeState($ctx, $user->fresh(), 402, '3');
        $this->assertSame('service_alerts_users', app(BotStateService::class)->get($user->fresh()));
        $handler->routeState($ctx, $user->fresh(), 402, '90');
        $this->assertSame('', app(BotStateService::class)->get($user->fresh()));
    }
}
