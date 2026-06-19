<?php

namespace Tests\Feature\Reseller;

use App\Models\DashboardUser;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerDefaultsProvisionTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_provision_applies_resellers_defaults_when_permissions_omitted(): void
    {
        app(SettingsStore::class)->set('resellers_defaults', [
            'permissions' => [
                'users.manage' => true,
                'plans.manage' => false,
                'receipts.review' => true,
                'services.manage' => false,
                'broadcast.send' => false,
                'users.bulk' => false,
                'marketing.lifecycle' => false,
            ],
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_dashboard_provision',
            'username' => 'defreseller',
            'password' => 'secret-pass',
            'parent_svp_user_id' => 100,
        ])->assertOk()->assertJsonPath('ok', true);

        $dash = DashboardUser::query()->where('username', 'defreseller')->first();
        $this->assertNotNull($dash);
        $perms = $dash->permissions_json;
        $this->assertIsArray($perms);
        $this->assertTrue($perms['users.manage']);
        $this->assertFalse($perms['plans.manage']);
        $this->assertTrue($perms['receipts.review']);
        $this->assertFalse($perms['services.manage']);
    }

    public function test_admin_state_includes_resellers_defaults(): void
    {
        app(SettingsStore::class)->set('resellers_defaults', [
            'permissions' => ['users.manage' => true],
        ]);

        $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk()
            ->assertJsonPath('resellersDefaults.permissions.users.manage', true);
    }
}
