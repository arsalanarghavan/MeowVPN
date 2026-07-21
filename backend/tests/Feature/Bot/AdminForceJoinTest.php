<?php

namespace Tests\Feature\Bot;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminForceJoinTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    public function test_force_join_publish_wizard_clears_state(): void
    {
        $admin = SvpUser::query()->create([
            'tg_user_id' => 7910,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        app(BotStateService::class)->set($admin, 'admin_force_join_publish', []);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 7910, 'Join our channel');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        Http::assertSentCount(1);
    }

    public function test_force_join_publish_uses_force_join_publish_mutate_not_membership(): void
    {
        app(SettingsStore::class)->merge([
            'telegram_bot_token' => '1:test-token',
            'force_join_channel_id' => '-10012345',
            'force_join_prompt' => 'Join now',
        ]);
        Http::fake([
            '*/sendMessage' => Http::response(['ok' => true, 'result' => ['message_id' => 9]], 200),
            '*' => Http::response(['ok' => true, 'result' => []], 200),
        ]);
        $admin = SvpUser::query()->create([
            'tg_user_id' => 7911,
            'status' => 'approved',
            'role' => 'admin',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        DashboardUser::query()->create([
            'username' => 'fj_admin_7911',
            'password' => Hash::make('secret'),
            'role' => 'admin',
            'svp_user_id' => (int) $admin->id,
        ]);
        app(BotStateService::class)->set($admin, 'admin_force_join_publish', []);
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->routeWizardText($ctx, $admin->fresh(), 7911, 'Please join');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'sendMessage')
                && (int) ($request['chat_id'] ?? 0) === -10012345;
        });
    }
}
