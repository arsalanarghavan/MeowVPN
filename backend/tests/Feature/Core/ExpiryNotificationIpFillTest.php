<?php

namespace Tests\Feature\Core;

use App\Models\SvpUser;
use App\Modules\Core\Services\ExpiryNotificationService;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ExpiryNotificationIpFillTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_ip_fill_alert_sent_when_distinct_ips_exceed_threshold(): void
    {
        app(SettingsStore::class)->set('notify_users_on', true);
        app(SettingsStore::class)->set('alert_ip_warn_min_distinct', 2);

        $user = SvpUser::query()->create([
            'username' => 'ipuser',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'user_id' => $user->id,
            'service_type' => 'xray',
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'test@svp.local',
            'panel_limit_ip' => 5,
            'total_traffic' => 1073741824,
            'used_traffic' => 0,
            'expires_at' => now()->addDays(30),
            'created_at' => now(),
        ]);
        $sid = (int) DB::table('svp_services')->max('id');
        foreach (['1.1.1.1', '2.2.2.2', '3.3.3.3'] as $ip) {
            DB::table('svp_service_ip_log')->insert([
                'service_id' => $sid,
                'ip' => $ip,
                'created_at' => now(),
            ]);
        }

        $notify = Mockery::mock(UserBotNotifyService::class);
        $notify->shouldReceive('sendToUser')->once();
        $this->app->instance(UserBotNotifyService::class, $notify);

        app(ExpiryNotificationService::class)->run();
    }
}
