<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminUsersBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    public function test_user_card_by_ui_callback(): void
    {
        $target = SvpUser::query()->create([
            'tg_user_id' => 77,
            'username' => 'cust77',
            'status' => 'approved',
            'balance' => 5000,
            'created_at' => now(),
        ]);
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleCallback($ctx, ['pnl', 'ui', (string) $target->id], $admin, 9001, 0);
        Http::assertSentCount(1);
    }

    public function test_legacy_unblock_alias(): void
    {
        $target = SvpUser::query()->create([
            'tg_user_id' => 78,
            'status' => 'blocked',
            'created_at' => now(),
        ]);
        $admin = SvpUser::query()->create([
            'tg_user_id' => 9001,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->handleUserAction($ctx, $admin, 9001, 'unb', (int) $target->id);
        Http::assertSentCount(1);
    }
}
