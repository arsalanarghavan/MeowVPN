<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminInboundCompleteTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [8000]);
    }

    protected function admin(): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => 8000,
            'status' => 'approved',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    public function test_xui_panels_tab_opens(): void
    {
        DB::table('svp_panels')->insert([
            'label' => 'Panel-A',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->openTab($ctx, 8000, $admin, 'xui_panels');
        Http::assertSentCount(1);
    }

    public function test_configs_tab_opens(): void
    {
        DB::table('svp_panels')->insert([
            'label' => 'Panel-B',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->openTab($ctx, 8000, $admin, 'configs');
        Http::assertSentCount(1);
    }

    public function test_l2tp_servers_tab_opens(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->openTab($ctx, 8000, $admin, 'l2tp_servers');
        Http::assertSentCount(1);
    }

    public function test_configs_batch_wizard_applies_mutate_and_clears_state(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'BatchPanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $owner = SvpUser::query()->create([
            'tg_user_id' => 8003,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            ['user_id' => $owner->id, 'panel_id' => $panelId, 'inbound_id' => 1, 'email' => 'a@test', 'created_at' => now()],
            ['user_id' => $owner->id, 'panel_id' => $panelId, 'inbound_id' => 1, 'email' => 'b@test', 'created_at' => now()],
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminInboundHandler::class);
        $handler->routeText($ctx, $admin, 8000, 'Batch clients');
        $this->assertSame('admin_inbound_configs_batch', app(BotStateService::class)->get($admin->fresh()));
        $handler->routeState($ctx, $admin->fresh(), 8000, (string) $panelId);
        $handler->routeState($ctx, $admin->fresh(), 8000, '1|noop|a@test,b@test');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $batch = app(ConfigsSyncService::class)->clientsBatch([
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'action' => 'noop',
            'emails' => ['a@test', 'b@test'],
        ]);
        $this->assertTrue(! empty($batch['ok']));
        $this->assertGreaterThanOrEqual(1, (int) ($batch['processed'] ?? 0));
        Http::assertSentCount(3);
    }

    public function test_configs_patch_wizard_updates_linked_service_note(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'PatchPanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $owner = SvpUser::query()->create([
            'tg_user_id' => 8002,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $owner->id,
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'email' => 'patch@test',
            'created_at' => now(),
        ]);
        $clientId = (int) DB::table('svp_panel_inbound_clients')->insertGetId([
            'panel_id' => $panelId,
            'inbound_id' => 1,
            'email' => 'patch@test',
            'comment' => '',
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminInboundHandler::class);
        $handler->routeText($ctx, $admin, 8000, 'Patch client');
        $handler->routeState($ctx, $admin->fresh(), 8000, (string) $clientId);
        $handler->routeState($ctx, $admin->fresh(), 8000, '{"service_note":"bot-patched"}');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame('bot-patched', (string) DB::table('svp_services')->where('id', $svcId)->value('service_note'));
    }

    public function test_configs_sync_wizard_triggers_sync_service(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'SyncPanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $this->mock(ConfigsSyncService::class, function ($mock) use ($panelId) {
            $mock->shouldReceive('syncPanelToDb')
                ->once()
                ->with($panelId, true)
                ->andReturn(['ok' => true]);
        });
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminInboundHandler::class);
        $handler->routeText($ctx, $admin, 8000, 'Sync configs');
        $handler->routeState($ctx, $admin->fresh(), 8000, (string) $panelId);
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        Http::assertSentCount(2);
    }

    public function test_panel_delete_confirm_deactivates_panel(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'DelPanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminInboundHandler::class);
        $handler->routeText($ctx, $admin, 8000, 'Delete panel');
        $handler->routeState($ctx, $admin->fresh(), 8000, (string) $panelId);
        $handler->routeState($ctx, $admin->fresh(), 8000, 'yes');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame(0, (int) DB::table('svp_panels')->where('id', $panelId)->value('active'));
    }

    public function test_panel_delete_callback_deactivates_panel(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'CbDelPanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminInboundHandler::class)->handleCallback($ctx, ['pnl', 'inb', 'pd', (string) $panelId, 'y'], $admin, 8000);
        $this->assertSame(0, (int) DB::table('svp_panels')->where('id', $panelId)->value('active'));
        Http::assertSentCount(1);
    }
}
