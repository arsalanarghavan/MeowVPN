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

class ExpiryNotificationAfterExpireTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_after_expire_alert_uses_wp_setting_key(): void
    {
        app(SettingsStore::class)->merge([
            'notify_user_expiry' => true,
            'notify_user_after_expire' => true,
            'purge_expired_grace_hours' => 9999,
        ]);

        $user = SvpUser::query()->create([
            'username' => 'expuser',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'user_id' => $user->id,
            'service_type' => 'xray',
            'email' => 'exp@svp.local',
            'total_traffic' => 1073741824,
            'used_traffic' => 0,
            'expires_at' => now()->subDay(),
            'created_at' => now(),
        ]);

        $notify = Mockery::mock(UserBotNotifyService::class);
        $notify->shouldReceive('sendToUser')->once()->withArgs(function ($u, $msg) {
            return str_contains($msg, 'منقضی شده');
        });
        $this->app->instance(UserBotNotifyService::class, $notify);

        app(ExpiryNotificationService::class)->run();
    }
}
