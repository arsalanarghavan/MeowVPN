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

class ServiceTransferLimitTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_svc_xf_callback_sets_transfer_state(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 710,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svc = SvpService::query()->create([
            'user_id' => $user->id,
            'email' => 'xf@test',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->handleCallback($ctx, $user, [
            'action' => 'xf',
            'svc_id' => (int) $svc->id,
            'chat_id' => 710,
        ]);
        $this->assertSame('service_transfer', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_transfer_route_state_uses_target_param(): void
    {
        $owner = SvpUser::query()->create([
            'tg_user_id' => 711,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $target = SvpUser::query()->create([
            'tg_user_id' => 712,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $owner->id,
            'email' => 'xfer@test',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($owner, 'service_transfer', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->routeState($ctx, $owner->fresh(), 711, (string) $target->id);
        $this->assertSame('', app(BotStateService::class)->get($owner->fresh()));
        $this->assertSame($target->id, (int) DB::table('svp_services')->where('id', $svcId)->value('user_id'));
        Http::assertSentCount(1);
    }

    public function test_svc_li_callback_sets_limit_ip_state(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 713,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svc = SvpService::query()->create([
            'user_id' => $user->id,
            'email' => 'li@test',
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->handleCallback($ctx, $user, [
            'action' => 'li',
            'svc_id' => (int) $svc->id,
            'chat_id' => 713,
        ]);
        $this->assertSame('service_limit_ip', app(BotStateService::class)->get($user->fresh()));
    }

    public function test_limit_ip_route_state_mutates_service(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 714,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'limit@test',
            'limit_ip' => 0,
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_limit_ip', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        app(ServiceHandler::class)->routeState($ctx, $user->fresh(), 714, '2');
        $this->assertSame('', app(BotStateService::class)->get($user->fresh()));
        $this->assertSame(2, (int) DB::table('svp_services')->where('id', $svcId)->value('limit_ip'));
        Http::assertSentCount(1);
    }
}
