<?php

namespace Tests\Feature\Http;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class ResellerHttpPermissionTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        $this->setModuleEnabled('xui_panel', true);
    }

    public function test_reseller_without_services_manage_cannot_access_panel_routes(): void
    {
        $user = DashboardUser::query()->where('username', 'reseller')->first();
        $user->permissions_json = ['users.manage' => true, 'services.manage' => false];
        $user->save();
        $this->actingAs($user);

        $this->getJson('/api/v1/admin/configs-snapshot?panel_id=1')
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_perm');
    }

    public function test_reseller_without_users_bulk_cannot_list_bulk_jobs(): void
    {
        $user = DashboardUser::query()->where('username', 'reseller')->first();
        $user->permissions_json = ['users.manage' => true, 'users.bulk' => false];
        $user->save();
        $this->actingAs($user);

        $this->getJson('/api/v1/admin/users-bulk-jobs')
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_perm');
    }

    public function test_reseller_without_broadcast_send_cannot_access_broadcast_queue(): void
    {
        $this->setModuleEnabled('marketing', true);
        $user = DashboardUser::query()->where('username', 'reseller')->first();
        $user->permissions_json = ['users.manage' => true, 'broadcast.send' => false];
        $user->save();
        $this->actingAs($user);

        $this->getJson('/api/v1/admin/broadcast-queue')
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_perm');
    }
}
