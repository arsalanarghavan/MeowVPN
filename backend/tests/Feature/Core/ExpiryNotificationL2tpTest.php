<?php

namespace Tests\Feature\Core;

use App\Models\SvpUser;
use App\Modules\Core\Services\ExpiryNotificationService;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ExpiryNotificationL2tpTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_expiry_syncs_l2tp_usage(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'u2',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'user_id' => $user->id,
            'service_type' => 'l2tp',
            'l2tp_server_id' => 1,
            'l2tp_username' => 'l2tpuser',
            'expires_at' => now()->addDays(5),
            'created_at' => now(),
        ]);

        $l2tp = Mockery::mock(L2tpProvisionerService::class);
        $l2tp->shouldReceive('refreshUsage')->once()->andReturn(1024);
        $l2tp->shouldReceive('deleteExpiredUser')->never();
        $this->app->instance(L2tpProvisionerService::class, $l2tp);

        $notify = Mockery::mock(UserBotNotifyService::class);
        $notify->shouldReceive('sendToUser')->zeroOrMoreTimes();
        $this->app->instance(UserBotNotifyService::class, $notify);

        app(ExpiryNotificationService::class)->run();
    }
}
