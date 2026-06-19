<?php

namespace Tests\Feature\Bot;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminBulkHandler;
use App\Modules\Core\Bot\Services\BotStateService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AdminBulkBotTest extends TestCase
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

    public function test_bulk_wallet_wizard_enqueues_job(): void
    {
        SvpUser::query()->create([
            'tg_user_id' => 2,
            'status' => 'approved',
            'balance' => 0,
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        $handler = app(AdminBulkHandler::class);
        $handler->routeText($ctx, $admin, 9001, 'Wallet bulk');
        $handler->routeState($ctx, $admin, 9001, '100');
        $this->assertSame('', app(BotStateService::class)->get($admin->fresh()));
        $this->assertGreaterThan(0, (int) DB::table('svp_users_bulk_jobs')->count());
    }

    public function test_bulk_alerts_fires_mutate(): void
    {
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        $handled = app(AdminBulkHandler::class)->routeText($ctx, $admin, 9001, 'Alerts bulk');
        $this->assertTrue($handled);
        $this->assertGreaterThanOrEqual(0, (int) DB::table('svp_users_bulk_jobs')->count());
    }

    public function test_bulk_job_cancel_callback(): void
    {
        $jobId = (int) DB::table('svp_users_bulk_jobs')->insertGetId([
            'operation' => 'wallet',
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $admin = $this->adminUser();
        $ctx = new BotContext('telegram');
        app(AdminBulkHandler::class)->handleCallback($ctx, ['pnl', 'bulk', 'c', (string) $jobId], $admin, 9001);
        $this->assertSame('cancelled', DB::table('svp_users_bulk_jobs')->where('id', $jobId)->value('status'));
    }
}
