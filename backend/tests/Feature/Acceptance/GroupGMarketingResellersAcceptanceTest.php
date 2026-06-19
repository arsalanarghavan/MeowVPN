<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group G — Marketing & Resellers */
class GroupGMarketingResellersAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_broadcast_queue(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/broadcast-queue')
            ->assertOk();
    }

    public function test_marketing_rule_save_admin(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_save',
            'name' => 'Test rule',
            'trigger' => 'idle_days',
            'trigger_days' => 7,
        ])->assertOk();
    }

    public function test_reseller_permissions_save(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_permissions_save',
            'svp_user_id' => 100,
            'permissions' => ['users.manage' => true],
        ])->assertOk();
    }

    public function test_marketing_lifecycle_forbidden_without_perm(): void
    {
        $user = DashboardUser::query()->where('username', 'admin')->first();
        $user->role = 'reseller';
        $user->permissions_json = ['users.manage' => true];
        $user->save();
        $this->actingAs($user);

        $this->postJson('/api/v1/admin/mutate', ['op' => 'marketing_rule_save', 'name' => 'x'])
            ->assertForbidden();
    }

    public function test_referral_reports_tab(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=referral_reports')
            ->assertOk()
            ->assertJsonStructure(['referralStats']);
    }

    public function test_reseller_reports_tab(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=reseller_reports')
            ->assertOk()
            ->assertJsonStructure(['resellerReportsStats', 'resellerReportsDaily']);
    }
}
