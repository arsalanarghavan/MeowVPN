<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Services\AdminAlertsService;
use App\Modules\Core\Services\AdminNotifyService;
use App\Modules\Relay\Services\TelegramRelayService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §18 — BackupFailed, RelayUnreachable, WebhookQueueBacklog alerts (v13). */
class AdminAlertsExtendedTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        Cache::flush();
    }

    public function test_backup_stale_triggers_notify(): void
    {
        app(SettingsStore::class)->set('backup_last_built_at', time() - 86400 * 7);

        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->once();
        $this->app->instance(AdminNotifyService::class, $notify);

        app(AdminAlertsService::class)->run();
    }

    public function test_webhook_queue_backlog_at_threshold_triggers_notify(): void
    {
        config(['svp.inbound_queue_alert_threshold' => 2]);
        foreach ([1, 2, 3] as $i) {
            DB::table('svp_inbound_queue')->insert([
                'status' => 'pending',
                'platform' => 'telegram',
                'update_json' => json_encode(['update_id' => $i]),
                'created_at' => now(),
            ]);
        }

        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->once();
        $this->app->instance(AdminNotifyService::class, $notify);

        app(AdminAlertsService::class)->run();
    }

    public function test_relay_unreachable_after_threshold_triggers_notify(): void
    {
        $this->setModuleEnabled('relay', true);
        app(SettingsStore::class)->merge([
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'secret',
        ]);
        config(['svp.relay_alert_fail_threshold' => 1]);

        $relay = Mockery::mock(TelegramRelayService::class);
        $relay->shouldReceive('isEnabled')->andReturn(true);
        $relay->shouldReceive('health')->andReturn(['ok' => false]);
        $this->app->instance(TelegramRelayService::class, $relay);

        $notify = Mockery::mock(AdminNotifyService::class);
        $notify->shouldReceive('notifyAdmins')->once();
        $this->app->instance(AdminNotifyService::class, $notify);

        app(AdminAlertsService::class)->run();
    }
}
