<?php

namespace Tests\Feature\XuiPanel;

use App\Modules\Core\Services\UserBotNotifyService;
use App\Modules\XuiPanel\Services\PurgeExpiredService;
use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PurgeExpiredNotifyTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_warn_notification_on_matching_warn_day(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => true,
            'purge_expired_grace_days' => 7,
            'purge_expired_warn_days' => [3, 1, 0],
            'purge_expired_notify_user' => true,
        ]);
        DB::table('svp_users')->insert([
            'id' => 1,
            'tg_user_id' => 100,
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_panels')->insert(['id' => 1, 'name' => 'p1', 'created_at' => now()]);
        DB::table('svp_services')->insert([
            'id' => 1,
            'user_id' => 1,
            'panel_id' => 1,
            'inbound_id' => 5,
            'email' => 'u@test',
            'service_type' => 'xray',
            'expires_at' => now()->subDays(4),
            'created_at' => now(),
        ]);

        $notify = Mockery::mock(UserBotNotifyService::class);
        $notify->shouldReceive('sendToUser')->once();
        $this->app->instance(UserBotNotifyService::class, $notify);
        $xui = Mockery::mock(XuiClient::class);
        $this->app->instance(XuiClient::class, $xui);

        $stats = app(PurgeExpiredService::class)->runBatch(30, 'cron', false);

        $this->assertSame(1, $stats['warned']);
        $this->assertSame(0, $stats['purged']);
    }
}
