<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminInboundBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
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

    public function test_configs_tab_lists_panels(): void
    {
        DB::table('svp_panels')->insert([
            'label' => 'P1',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->openTab($ctx, 9001, $admin, 'configs');
        $this->assertGreaterThanOrEqual(1, (int) DB::table('svp_panels')->count());
    }

    public function test_inbound_link_state_starts_wizard(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->handleCallback($ctx, ['pnl', 'inb', 'lk', '1', '2', '3'], $admin, 9001);
        $this->assertSame('admin_inbound_link', app(BotStateService::class)->get($admin->fresh()));
    }

    public function test_inbound_client_toggle_callback(): void
    {
        $panelId = DB::table('svp_panels')->insertGetId([
            'label' => 'P1',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'email' => 'u@test',
            'enable' => 1,
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->handleCallback($ctx, ['pnl', 'inb', 'cl', 'tg', (string) $panelId, '1', 'u@test'], $admin, 9001);
        $this->assertGreaterThanOrEqual(1, (int) DB::table('svp_panel_inbound_clients')->count());
    }

    public function test_panel_picker_callback(): void
    {
        $panelId = DB::table('svp_panels')->insertGetId([
            'label' => 'Main',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->handleCallback($ctx, ['pnl', 'inb', 'pn', (string) $panelId], $admin, 9001);
        $this->assertSame($panelId, (int) DB::table('svp_panels')->value('id'));
    }
}
