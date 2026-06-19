<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminUserServiceOpsTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    protected function admin(): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    protected function serviceId(int $slots = 3): int
    {
        $owner = SvpUser::query()->create([
            'tg_user_id' => 7920,
            'status' => 'approved',
            'created_at' => now(),
        ]);

        return (int) DB::table('svp_services')->insertGetId([
            'user_id' => $owner->id,
            'email' => 'admin-ops@test',
            'client_slots' => $slots,
            'created_at' => now(),
        ]);
    }

    public function test_reduce_slots_callback_sets_state(): void
    {
        $admin = $this->admin();
        $svcId = $this->serviceId();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'svc', 'rsl', (string) $svcId], $admin, 9001, 0);
        $this->assertSame('admin_users_service_reduce_slots', app(BotStateService::class)->get($admin->fresh()));
    }

    public function test_reduce_slots_route_state_mutates_service(): void
    {
        $admin = $this->admin();
        $svcId = $this->serviceId(5);
        app(BotStateService::class)->set($admin, 'admin_users_service_reduce_slots', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->routeState($ctx, $admin->fresh(), 9001, '1');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame(4, (int) DB::table('svp_services')->where('id', $svcId)->value('client_slots'));
    }

    public function test_transfer_callback_sets_state(): void
    {
        $admin = $this->admin();
        $svcId = $this->serviceId();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'svc', 'xf', (string) $svcId], $admin, 9001, 0);
        $this->assertSame('admin_users_service_transfer', app(BotStateService::class)->get($admin->fresh()));
    }

    public function test_limit_ip_callback_sets_state(): void
    {
        $admin = $this->admin();
        $svcId = $this->serviceId();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'svc', 'li', (string) $svcId], $admin, 9001, 0);
        $this->assertSame('admin_users_service_limit_ip', app(BotStateService::class)->get($admin->fresh()));
    }

    public function test_alerts_patch_json_wizard_updates_service(): void
    {
        $admin = $this->admin();
        $svcId = $this->serviceId();
        app(BotStateService::class)->set($admin, 'admin_users_service_alerts', ['service_id' => $svcId]);
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->routeState($ctx, $admin->fresh(), 9001, '{"traffic":true,"expiry":false}');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $alerts = json_decode((string) DB::table('svp_services')->where('id', $svcId)->value('alerts_json'), true);
        $this->assertSame(true, $alerts['traffic'] ?? null);
        $this->assertSame(false, $alerts['expiry'] ?? null);
    }
}
