<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminHandlerRegistry;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminHandlerRegistryTest extends TestCase
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

    public function test_pnl_dash_routes_to_stats(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'dash'], $admin, 9001, 0, 9001);
        Http::assertSentCount(1);
    }

    public function test_pnl_fin_customer_charges_callback(): void
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
        Http::assertSentCount(1);
    }

    public function test_legacy_blk_alias_blocks_user(): void
    {
        $target = SvpUser::query()->create([
            'tg_user_id' => 42,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'blk', (string) $target->id], $admin, 9001, 0, 9001);
        Http::assertSentCount(1);
    }

    public function test_pnl_lg_routes_to_logs(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'lg'], $admin, 9001, 0, 9001);
        Http::assertSentCount(1);
    }
}
