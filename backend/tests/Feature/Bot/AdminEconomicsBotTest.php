<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminEconomicsHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminEconomicsBotTest extends TestCase
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

    public function test_mark_paid_prompt_sets_state(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handled = app(AdminEconomicsHandler::class)->routeText($ctx, $admin, 9001, 'Mark paid');
        $this->assertTrue($handled);
        $this->assertSame('admin_economics_mark_paid', app(BotStateService::class)->get($admin->fresh()));
    }

    public function test_line_add_wizard_accepts_panel_id(): void
    {
        $panelId = DB::table('svp_panels')->insertGetId([
            'label' => 'Eco',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_economics_line_add', ['step' => 'panel']);
        $ctx = new BotContext('telegram');
        app(AdminEconomicsHandler::class)->routeState($ctx, $admin->fresh(), 9001, (string) $panelId);
        $data = app(BotStateService::class)->data($admin->fresh());
        $this->assertSame('line', $data['step'] ?? '');
        $this->assertSame($panelId, (int) ($data['panel_id'] ?? 0));
    }

    public function test_line_edit_wizard_accepts_line_id(): void
    {
        $panelId = DB::table('svp_panels')->insertGetId([
            'label' => 'Eco',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $lineId = DB::table('svp_panel_economics_lines')->insertGetId([
            'panel_id' => $panelId,
            'label' => 'Rent',
            'amount' => 100,
            'active' => 1,
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        app(BotStateService::class)->set($admin, 'admin_economics_edit_line', ['step' => 'panel']);
        $ctx = new BotContext('telegram');
        app(AdminEconomicsHandler::class)->routeState($ctx, $admin->fresh(), 9001, (string) $panelId);
        $data = app(BotStateService::class)->data($admin->fresh());
        $this->assertSame('line_id', $data['step'] ?? '');
        app(AdminEconomicsHandler::class)->routeState($ctx, $admin->fresh(), 9001, (string) $lineId);
        $data = app(BotStateService::class)->data($admin->fresh());
        $this->assertSame('line', $data['step'] ?? '');
        $this->assertSame($lineId, (int) ($data['line_id'] ?? 0));
    }

    public function test_delete_line_prompt_sets_state(): void
    {
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handled = app(AdminEconomicsHandler::class)->routeText($ctx, $admin, 9001, 'Delete line');
        $this->assertTrue($handled);
        $this->assertSame('admin_economics_delete_line', app(BotStateService::class)->get($admin->fresh()));
    }
}
