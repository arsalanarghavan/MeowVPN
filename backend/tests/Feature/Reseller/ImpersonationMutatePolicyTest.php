<?php

namespace Tests\Feature\Reseller;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ImpersonationMutatePolicyTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_impersonating_admin_blocked_on_admin_only_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'wholesale_line_save',
            'panel_id' => 1,
            'label' => 'X',
            'price_per_gb' => 100,
        ])->assertOk()->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'forbidden_op');
    }

    public function test_impersonating_admin_may_run_reseller_op_with_perm(): void
    {
        $dash = DashboardUser::query()->where('username', 'reseller')->first();
        $dash->permissions_json = ['users.manage' => true, 'plans.manage' => true];
        $dash->save();

        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'id' => 1,
            'name' => 'Reseller Plan Edit',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
