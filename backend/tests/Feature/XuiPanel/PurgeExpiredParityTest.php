<?php

namespace Tests\Feature\XuiPanel;

use App\Modules\XuiPanel\Services\PurgeExpiredService;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PurgeExpiredParityTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_cron_skips_when_purge_disabled(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => false,
        ]);
        $this->seedExpiredService(10);

        $stats = app(PurgeExpiredService::class)->runBatch(30, 'cron', false);

        $this->assertSame(0, $stats['purged']);
        $this->assertNull(DB::table('svp_services')->where('id', 1)->value('deleted_at'));
    }

    public function test_manual_run_can_ignore_enabled_flag(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => false,
            'purge_expired_grace_days' => 1,
        ]);
        $this->seedExpiredService(10);
        $this->mockXuiDelete();

        $stats = app(PurgeExpiredService::class)->runBatch(30, 'manual', true);

        $this->assertSame(1, $stats['purged']);
    }

    public function test_l2tp_services_are_skipped(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => true,
            'purge_expired_grace_days' => 1,
        ]);
        DB::table('svp_users')->insert([
            'id' => 1,
            'tg_user_id' => 100,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'id' => 1,
            'user_id' => 1,
            'panel_id' => 1,
            'inbound_id' => 5,
            'email' => 'l2tp@test',
            'service_type' => 'l2tp',
            'expires_at' => now()->subDays(10),
            'created_at' => now(),
        ]);

        $stats = app(PurgeExpiredService::class)->runBatch(30, 'cron', false);

        $this->assertSame(0, $stats['purged']);
    }

    public function test_grace_days_blocks_early_purge(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => true,
            'purge_expired_grace_days' => 7,
        ]);
        $this->seedExpiredService(2);

        $stats = app(PurgeExpiredService::class)->runBatch(30, 'cron', false);

        $this->assertSame(0, $stats['purged']);
    }

    public function test_purge_after_grace_and_returns_stats(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => true,
            'purge_expired_grace_days' => 3,
            'purge_expired_notify_user' => false,
        ]);
        $this->seedExpiredService(10);
        $this->mockXuiDelete();

        $stats = app(PurgeExpiredService::class)->runBatch(30, 'cron', false);

        $this->assertSame(1, $stats['purged']);
        $this->assertSame(3, $stats['grace']);
        $this->assertNotNull(DB::table('svp_services')->where('id', 1)->value('deleted_at'));
    }

    protected function seedExpiredService(int $daysAgo): void
    {
        DB::table('svp_users')->insert([
            'id' => 1,
            'tg_user_id' => 100,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_panels')->insert([
            'id' => 1,
            'name' => 'p1',
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'id' => 1,
            'user_id' => 1,
            'panel_id' => 1,
            'inbound_id' => 5,
            'email' => 'u@test',
            'service_type' => 'xray',
            'expires_at' => now()->subDays($daysAgo),
            'created_at' => now(),
        ]);
    }

    protected function mockXuiDelete(): void
    {
        $mock = Mockery::mock(XuiClient::class);
        $mock->shouldReceive('deleteClient')->andReturn(['ok' => true]);
        $this->app->instance(XuiClient::class, $mock);
    }
}
