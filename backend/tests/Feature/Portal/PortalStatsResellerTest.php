<?php

namespace Tests\Feature\Portal;

use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PortalStatsResellerTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'portal_link_secret'],
            ['value' => 'test-secret', 'updated_at' => now()]
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_reseller_stats_scoped_to_moderatable_users(): void
    {
        $reseller = SvpUser::query()->create([
            'username' => 'rs1',
            'role' => 'reseller',
            'status' => 'approved',
            'tg_user_id' => 10,
            'created_at' => now(),
        ]);
        $scoped = SvpUser::query()->create([
            'username' => 'scoped',
            'role' => 'user',
            'status' => 'approved',
            'signup_reseller_svp_id' => $reseller->id,
            'created_at' => now(),
        ]);
        SvpUser::query()->create([
            'username' => 'other',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $scope = Mockery::mock(ResellerScopeService::class);
        $scope->shouldReceive('moderatableUserIds')->with((int) $reseller->id)->andReturn([(int) $scoped->id]);
        $scope->shouldReceive('allowedPanelIdsFor')->andReturn([]);
        $this->app->instance(ResellerScopeService::class, $scope);

        $signed = app(PortalLinkService::class)->buildAdminLink((int) $reseller->id, 3600);

        $this->postJson('/api/v1/portal/admin', array_merge($signed, ['op' => 'stats']))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.users.users_total', 1);
    }
}
