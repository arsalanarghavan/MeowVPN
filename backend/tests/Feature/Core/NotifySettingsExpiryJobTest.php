<?php

namespace Tests\Feature\Core;

use App\Models\SvpUser;
use App\Modules\Core\Jobs\ExpiryJob;
use App\Modules\Core\Services\UserBotNotifyService;
use App\Services\SettingsStore;
use Database\Seeders\SvpTestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class NotifySettingsExpiryJobTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->seed(SvpTestDataSeeder::class);
    }

    public function test_expiry_job_skips_when_notify_user_expiry_disabled(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'notify_user_expiry' => false,
            'notify_expiry_days' => [0],
        ]);

        $user = SvpUser::query()->first();
        $this->assertNotNull($user);

        $svcId = (int) DB::table('svp_services')->insertGetId([
            'user_id' => $user->id,
            'service_type' => 'xray',
            'email' => 'expiry-off@test.local',
            'total_traffic' => 1073741824,
            'used_traffic' => 0,
            'expires_at' => now()->endOfDay(),
            'created_at' => now(),
        ]);

        $notify = Mockery::mock(UserBotNotifyService::class);
        $notify->shouldNotReceive('sendToUser');
        $this->app->instance(UserBotNotifyService::class, $notify);

        (new ExpiryJob)->handle();

        $this->assertDatabaseHas('svp_services', ['id' => $svcId]);
    }
}
