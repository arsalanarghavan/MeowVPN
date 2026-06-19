<?php

namespace Tests\Feature\Core;

use App\Models\SvpUser;
use App\Modules\Core\Services\AutorenewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class AutorenewL2tpSkipTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_autorenew_skips_l2tp_services(): void
    {
        $user = SvpUser::query()->create([
            'username' => 'u1',
            'role' => 'user',
            'status' => 'approved',
            'balance' => 100000,
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'user_id' => $user->id,
            'service_type' => 'l2tp',
            'autorenew' => 1,
            'expires_at' => now()->subHour(),
            'plan_id' => 1,
            'created_at' => now(),
        ]);
        $before = (float) $user->fresh()->balance;

        app(AutorenewService::class)->run();

        $this->assertSame($before, (float) SvpUser::query()->find($user->id)->balance);
    }
}
