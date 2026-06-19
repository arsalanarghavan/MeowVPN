<?php

namespace Tests\Feature\Reseller;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerProvisionTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_dashboard_provision_creates_svp_user_dashboard_and_closure(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_dashboard_provision',
            'username' => 'subreseller',
            'password' => 'secret-pass',
            'parent_svp_user_id' => 100,
            'permissions' => ['users.manage' => true],
        ])->assertOk()->assertJsonPath('ok', true);

        $svpUser = SvpUser::query()->where('username', 'subreseller')->first();
        $this->assertNotNull($svpUser);
        $this->assertSame('reseller', $svpUser->role);
        $this->assertSame(100, (int) $svpUser->invited_by);

        $dash = DashboardUser::query()->where('username', 'subreseller')->first();
        $this->assertNotNull($dash);
        $this->assertSame('reseller', $dash->role);
        $this->assertSame($svpUser->id, (int) $dash->svp_user_id);

        $this->assertDatabaseHas('svp_reseller_closure', [
            'ancestor_id' => 100,
            'descendant_id' => $svpUser->id,
        ]);

        $this->assertDatabaseHas('svp_reseller_bot_profiles', [
            'reseller_svp_user_id' => $svpUser->id,
        ]);
    }
}
