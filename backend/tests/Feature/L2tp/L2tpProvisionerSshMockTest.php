<?php

namespace Tests\Feature\L2tp;

use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\L2tp\Services\L2tpSshRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** L2tpProvisionerService SSH mock integration (v18). */
class L2tpProvisionerSshMockTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_refresh_usage_calls_ssh_and_updates_traffic(): void
    {
        $ssh = Mockery::mock(L2tpSshRunner::class);
        $ssh->shouldReceive('exec')
            ->once()
            ->andReturn(['ok' => true, 'stdout' => '123456789', 'stderr' => '']);

        $this->app->instance(L2tpSshRunner::class, $ssh);

        DB::table('svp_l2tp_servers')->where('id', 1)->update([
            'usage_cmd_template' => 'cat /var/log/usage/{username}',
        ]);
        DB::table('svp_services')->where('id', 1)->update([
            'l2tp_username' => 'testuser',
            'l2tp_server_id' => 1,
        ]);

        $svc = DB::table('svp_services')->where('id', 1)->first();
        $bytes = app(L2tpProvisionerService::class)->refreshUsage($svc);

        $this->assertSame(123456789, $bytes);
        $this->assertSame(123456789, (int) DB::table('svp_services')->where('id', 1)->value('used_traffic'));
    }

    public function test_delete_expired_user_ssh_failure_returns_false(): void
    {
        $ssh = Mockery::mock(L2tpSshRunner::class);
        $ssh->shouldReceive('exec')
            ->once()
            ->andReturn(['ok' => false, 'stdout' => '', 'stderr' => 'permission denied']);

        $this->app->instance(L2tpSshRunner::class, $ssh);

        DB::table('svp_services')->where('id', 1)->update([
            'l2tp_username' => 'expireduser',
            'l2tp_server_id' => 1,
        ]);

        $svc = DB::table('svp_services')->where('id', 1)->first();
        $this->assertFalse(app(L2tpProvisionerService::class)->deleteExpiredUser($svc));
    }
}
