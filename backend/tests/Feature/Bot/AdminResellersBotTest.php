<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminResellersHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminResellersBotTest extends TestCase
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

    public function test_resellers_tab_lists(): void
    {
        SvpUser::query()->create([
            'tg_user_id' => 9300,
            'status' => 'approved',
            'role' => 'reseller',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        app(AdminResellersHandler::class)->openTab($ctx, 9001, $admin, 'resellers');
        Http::assertSentCount(1);
    }

    public function test_panel_prices_wizard_saves_price_per_gb(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'ResellerPricePanel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $reseller = SvpUser::query()->create([
            'tg_user_id' => 9310,
            'status' => 'approved',
            'role' => 'reseller',
            'created_at' => now(),
        ]);
        $admin = $this->admin();
        $ctx = new BotContext('telegram');
        $handler = app(AdminResellersHandler::class);
        $state = app(BotStateService::class);

        $handler->routeText($ctx, $admin, 9001, 'Panel prices');
        $handler->routeState($ctx, $admin->fresh(), 9001, (string) $reseller->id);
        $this->assertSame('admin_reseller_panel_prices', $state->get($admin->fresh()));

        $handler->routeState($ctx, $admin->fresh(), 9001, $panelId.'|1500.5');
        $this->assertSame('', $state->get($admin->fresh()));
        $this->assertDatabaseHas('svp_reseller_panel_prices', [
            'reseller_svp_user_id' => $reseller->id,
            'panel_id' => $panelId,
            'price_per_gb' => 1500.5,
        ]);
    }
}
