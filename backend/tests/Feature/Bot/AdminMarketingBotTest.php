<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminMarketingHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminMarketingBotTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
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

    public function test_broadcast_segment_step_after_text(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        $state = app(BotStateService::class);
        app(AdminMarketingHandler::class)->openBroadcastPrompt($ctx, 9001, $admin);
        app(AdminMarketingHandler::class)->handleBroadcastText($ctx, $admin->fresh(), 9001, 'Hello all');
        $this->assertSame('admin_broadcast', $state->get($admin->fresh()));
        $this->assertSame('segment', $state->data($admin->fresh())['step'] ?? '');
    }

    public function test_broadcast_segment_callback_sets_confirm(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        $state = app(BotStateService::class);
        $state->set($admin, 'admin_broadcast', ['step' => 'segment', 'bc_text' => 'Hi']);
        app(AdminMarketingHandler::class)->handleBroadcastCallback($ctx, $admin, 9001, ['pnl', 'bc', 'both']);
        $data = $state->data($admin->fresh());
        $this->assertSame('confirm', $data['step'] ?? '');
        $this->assertSame('both', $data['bc_targets'] ?? '');
    }

    public function test_discount_save_via_state(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        $handler = app(AdminMarketingHandler::class);
        $handler->routeText($ctx, $admin, 9001, 'New code');
        $handler->routeState($ctx, $admin, 9001, 'SAVE10');
        $handler->routeState($ctx, $admin, 9001, '15');
        $row = DB::table('svp_discount_codes')->where('code', 'SAVE10')->first();
        $this->assertNotNull($row);
    }
}
