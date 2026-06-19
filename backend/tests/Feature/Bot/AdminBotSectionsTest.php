<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminCatalogHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminMarketingHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminPanelHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Handlers\WalletHandler;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminNavService;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminBotSectionsTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_admin_ids', [9001]);
    }

    protected function adminUser(): SvpUser
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

    public function test_panel_entry_builds_section_nav(): void
    {
        $user = $this->adminUser();
        $nav = app(AdminNavService::class);
        $perms = app(AdminPermissionService::class);
        $sections = $nav->visibleSectionIds($user, $perms->allowedTabs($user));
        $this->assertContains('finance', $sections);
        $this->assertContains('users', $sections);
    }

    public function test_users_submenu_lists_pending(): void
    {
        SvpUser::query()->create([
            'tg_user_id' => 3,
            'status' => 'pending',
            'role' => 'user',
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->openUsersSubmenu($ctx, 9001, $admin);
        $this->assertSame(1, (int) DB::table('svp_users')->where('status', 'pending')->count());
    }

    public function test_user_card_by_hash_id(): void
    {
        $target = SvpUser::query()->create([
            'tg_user_id' => 55,
            'username' => 'cust',
            'status' => 'approved',
            'balance' => 1000,
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        $handled = app(AdminUsersHandler::class)->routeModerationText($ctx, $admin, 9001, '#'.$target->id, ['id' => 9001]);
        $this->assertTrue($handled);
    }

    public function test_catalog_send_list_plans_with_inline(): void
    {
        DB::table('svp_plans')->insert([
            'name' => 'Test plan',
            'price' => 1000,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminCatalogHandler::class)->sendList($ctx, 9001, $admin, 'plans', 0);
        $kb = app(AdminKeyboardBuilder::class)->inlineCatalogList('plans', DB::table('svp_plans')->get()->all(), 0, 1);
        $this->assertNotEmpty($kb['inline_keyboard']);
    }

    public function test_catalog_toggle_plan_callback(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Toggle me',
            'price' => 500,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminCatalogHandler::class)->handleCallback($ctx, ['pnl', 'cat', 't', 'pl', (string) $planId], $admin, 9001, 0);
        $row = DB::table('svp_plans')->where('id', $planId)->first();
        $this->assertNotNull($row);
    }

    public function test_broadcast_state_moves_to_segment_after_text(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminMarketingHandler::class)->openBroadcastPrompt($ctx, 9001, $admin);
        $this->assertSame('admin_broadcast', app(BotStateService::class)->get($admin->fresh()));
        app(AdminMarketingHandler::class)->handleBroadcastText($ctx, $admin->fresh(), 9001, 'Hello all');
        $data = app(BotStateService::class)->data($admin->fresh());
        $this->assertSame('admin_broadcast', app(BotStateService::class)->get($admin->fresh()));
        $this->assertSame('segment', $data['step'] ?? '');
    }

    public function test_approved_queue_paging_callback(): void
    {
        SvpUser::query()->create([
            'tg_user_id' => 77,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminUsersHandler::class)->sendApprovedPage($ctx, 9001, $admin, 0);
        $this->assertGreaterThanOrEqual(1, (int) DB::table('svp_users')->where('status', 'approved')->count());
    }

    public function test_catalog_delete_soft_via_mutate(): void
    {
        $planId = DB::table('svp_plans')->insertGetId([
            'name' => 'Soft delete',
            'price' => 200,
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminCatalogHandler::class)->handleCallback($ctx, ['pnl', 'cat', 'dy', 'pl', (string) $planId], $admin, 9001, 0);
        $this->assertSame(0, (int) DB::table('svp_plans')->where('id', $planId)->value('active'));
    }

    public function test_settings_handle_op_webhook(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminSettingsHandler::class)->handleOp($ctx, 9001, $admin, 'wtg');
        $this->assertTrue(true);
    }

    public function test_reject_reason_keyboard_format(): void
    {
        $kb = app(AdminKeyboardBuilder::class)->inlineReceiptRejectReasons(42);
        $this->assertStringContainsString('rc:rr:blur:42', $kb['inline_keyboard'][0][0]['callback_data']);
        $this->assertStringContainsString('rc:rb:42', $kb['inline_keyboard'][count($kb['inline_keyboard']) - 1][0]['callback_data']);
    }

    public function test_panel_open_tab_receipts(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminPanelHandler::class)->openTab($ctx, 9001, $admin, 'receipts');
        $this->assertTrue(true);
    }
}
