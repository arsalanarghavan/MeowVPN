<?php

namespace Tests\Feature\Mutate;

use App\Models\DashboardUser;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class ConfigsClientPolicyTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('xui_panel', true);
    }

    public function test_reseller_without_services_manage_cannot_configs_assign_plan(): void
    {
        $user = DashboardUser::query()->where('username', 'reseller')->first();
        $user->permissions_json = ['users.manage' => true, 'services.manage' => false];
        $user->save();
        $this->actingAs($user);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_assign_plan',
            'service_id' => 1,
            'plan_id' => 1,
        ])->assertForbidden()->assertJsonPath('message', 'forbidden_perm');
    }
}
