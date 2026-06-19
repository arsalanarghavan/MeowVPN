<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminBackupHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminFinanceHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminHandlerRegistry;
use App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminRelayHandler;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminHandlerRegistryCompleteTest extends TestCase
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

    public function test_pnl_relay_callback_routes_to_relay_handler(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'relay', 'st'], $admin, 9001, 0, 9001);
        $this->assertInstanceOf(AdminRelayHandler::class, app(AdminRelayHandler::class));
        Http::assertSentCount(1);
    }

    public function test_pnl_fin_callback_routes_to_finance_handler(): void
    {
        DB::table('svp_transactions')->insert([
            'user_id' => 1,
            'amount' => 1000,
            'type' => 'purchase',
            'status' => 'completed',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'fin', 'cc', '0', 'all'], $admin, 9001, 0, 9001);
        $this->assertInstanceOf(AdminFinanceHandler::class, app(AdminFinanceHandler::class));
        Http::assertSentCount(1);
    }

    public function test_pnl_inb_callback_routes_to_inbound_handler(): void
    {
        DB::table('svp_panels')->insert([
            'label' => 'RegPanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'inb'], $admin, 9001, 0, 9001);
        $this->assertInstanceOf(\App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler::class, app(\App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler::class));
        Http::assertSentCount(1);
    }

    public function test_pnl_bk_callback_routes_to_backup_handler(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'bk'], $admin, 9001, 0, 9001);
        $this->assertInstanceOf(AdminBackupHandler::class, app(AdminBackupHandler::class));
        Http::assertSentCount(1);
    }
}
