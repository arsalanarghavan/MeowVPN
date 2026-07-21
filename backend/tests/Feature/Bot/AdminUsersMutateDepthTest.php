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

class AdminUsersMutateDepthTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
        app(SettingsStore::class)->merge([
            'telegram_bot_token' => '1:test-token',
            'force_join_enabled' => true,
            'force_join_channel_id' => '-100123',
            'force_join_channel_url' => 'https://t.me/example',
        ]);
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

    public function test_membership_force_join_via_callback(): void
    {
        $pending = SvpUser::query()->create([
            'tg_user_id' => 7940,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'mem', (string) $pending->id, 'fj'], $admin, 9001, 0);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage');
        });
    }

    public function test_user_status_approve_via_membership_callback(): void
    {
        $pending = SvpUser::query()->create([
            'tg_user_id' => 7941,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'mem', (string) $pending->id, 'a'], $admin, 9001, 0);
        $this->assertSame('approved', (string) SvpUser::query()->find($pending->id)?->status);
    }

    public function test_service_toggle_disable_via_callback(): void
    {
        $owner = SvpUser::query()->create([
            'tg_user_id' => 7942,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $owner->id,
            'email' => 'toggle@test',
            'client_enabled' => true,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'svc', 'tg', (string) $svcId, '0'], $admin, 9001, 0);
        $this->assertFalse((bool) DB::table('svp_services')->where('id', $svcId)->value('client_enabled'));
    }

    public function test_balance_delta_credit_via_wizard(): void
    {
        if (! function_exists('bcadd')) {
            $this->markTestSkipped('bcmath extension required for user_balance_delta');
        }
        $target = SvpUser::query()->create([
            'tg_user_id' => 7943,
            'status' => 'approved',
            'balance' => 100,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_users_moderation', [
            'action' => 'credit',
            'target_uid' => $target->id,
        ]);
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->routeModerationText($ctx, $admin->fresh(), 9001, '50', []);
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame(150.0, (float) SvpUser::query()->find($target->id)?->balance);
    }

    public function test_receipt_action_reject_via_users_handler(): void
    {
        $user = SvpUser::query()->create([
            'tg_user_id' => 7944,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $txId = (int) DB::table('svp_transactions')->insertGetId([
            'user_id' => $user->id,
            'amount' => 9000,
            'type' => 'purchase',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $rcId = (int) DB::table('svp_receipts')->insertGetId([
            'user_id' => $user->id,
            'transaction_id' => $txId,
            'amount' => 9000,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'urcp', 'r', (string) $rcId], $admin, 9001, 0);
        Http::assertSentCount(1);
        $this->assertSame('pending', (string) DB::table('svp_receipts')->where('id', $rcId)->value('status'));
    }
}
