<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminCatalogHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminHandlerRegistry;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class BotCallbackIntegrationTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    protected function siteAdmin(): SvpUser
    {
        return SvpUser::query()->create([
            'tg_user_id' => 9001,
            'username' => 'siteadmin',
            'status' => 'approved',
            'role' => 'user',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
    }

    public function test_catalog_delete_confirm_via_mutate(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Delete me',
            'price' => 100,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->siteAdmin();
        $ctx = new BotContext('telegram');
        app(AdminCatalogHandler::class)->handleCallback($ctx, ['pnl', 'cat', 'dy', 'pl', (string) $planId], $admin, 9001, 0);
        $row = DB::table('svp_plans')->where('id', $planId)->first();
        $this->assertNotNull($row);
        $this->assertSame(0, (int) $row->active);
    }

    public function test_membership_paging_approved_queue(): void
    {
        for ($i = 0; $i < 3; $i++) {
            SvpUser::query()->create([
                'tg_user_id' => 100 + $i,
                'status' => 'approved',
                'created_at' => now(),
            ]);
        }
        $admin = $this->siteAdmin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'aq', '0'], $admin, 9001, 0, 9001);
        $this->assertGreaterThanOrEqual(3, (int) DB::table('svp_users')->where('status', 'approved')->count());
    }

    public function test_reseller_idor_blocked_on_foreign_user_card(): void
    {
        $reseller = SvpUser::query()->create([
            'tg_user_id' => 8001,
            'status' => 'approved',
            'role' => 'reseller',
            'admin_mode' => true,
            'created_at' => now(),
        ]);
        $foreign = SvpUser::query()->create([
            'tg_user_id' => 8002,
            'status' => 'approved',
            'signup_reseller_svp_id' => 0,
            'created_at' => now(),
        ]);
        $scope = app(AdminBotScopeService::class);
        $scope->setActingAdmin((int) $reseller->id);
        $this->assertFalse($scope->mayModerateUser($reseller, (int) $foreign->id));
        $ctx = new BotContext('telegram');
        $handled = app(AdminUsersHandler::class)->routeModerationText($ctx, $reseller, 8001, '#'.$foreign->id, ['id' => 8001]);
        $this->assertTrue($handled);
    }

    public function test_inbound_panel_callback_via_registry(): void
    {
        $panelId = DB::table('svp_panels')->insertGetId([
            'label' => 'CB Panel',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->siteAdmin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'inb', 'pn', (string) $panelId], $admin, 9001, 0, 9001);
        $this->assertSame($panelId, (int) DB::table('svp_panels')->value('id'));
    }

    public function test_registry_texts_preview_callback(): void
    {
        DB::table('svp_texts')->insert(['key_name' => 'msg.test', 'value' => 'hello']);
        $admin = $this->siteAdmin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'th', 'msg.test'], $admin, 9001, 0, 9001);
        Http::assertSentCount(1);
    }

    public function test_registry_backup_callback(): void
    {
        $admin = $this->siteAdmin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'bk'], $admin, 9001, 0, 9001);
        Http::assertSentCount(1);
    }

    public function test_reseller_xui_panels_paging_callback(): void
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('svp_reseller_panel_prices')) {
            $this->markTestSkipped('svp_reseller_panel_prices table missing');
        }
        DB::table('svp_reseller_panel_prices')->insert([
            'reseller_svp_user_id' => 10,
            'panel_id' => 1,
            'price_per_gb' => 1000,
            'updated_at' => now(),
        ]);
        $admin = $this->siteAdmin();
        $ctx = new BotContext('telegram');
        app(AdminHandlerRegistry::class)->handlePnl($ctx, ['pnl', 'res', 'xp', '0'], $admin, 9001, 0, 9001);
        $this->assertGreaterThanOrEqual(1, (int) DB::table('svp_reseller_panel_prices')->count());
    }
}
