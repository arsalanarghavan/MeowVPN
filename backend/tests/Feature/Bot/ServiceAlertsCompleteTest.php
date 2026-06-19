<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\ServiceHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ServiceAlertsCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_alerts_wizard_three_steps_then_patch(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 720,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'email' => 'alerts@test',
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($user, 'service_alerts_pct', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        $handler = app(ServiceHandler::class);
        $handler->routeState($ctx, $user->fresh(), 720, '80');
        $this->assertSame('service_alerts_exp', app(BotStateService::class)->get($user->fresh()));
        $handler->routeState($ctx, $user->fresh(), 720, '3');
        $this->assertSame('service_alerts_users', app(BotStateService::class)->get($user->fresh()));
        $handler->routeState($ctx, $user->fresh(), 720, '90');
        $this->assertSame('', app(BotStateService::class)->get($user->fresh()));
        $row = DB::table('svp_services')->where('id', $svcId)->first();
        $alerts = json_decode((string) ($row->alerts_json ?? ''), true);
        $this->assertIsArray($alerts);
        $this->assertSame(80, (int) ($alerts['alert_low_pct'] ?? 0));
        $this->assertSame('3', (string) ($alerts['alert_expiry_days'] ?? ''));
        $this->assertSame(90, (int) ($alerts['alert_ip_fill_pct'] ?? 0));
        Http::assertSentCount(3);
    }
}
