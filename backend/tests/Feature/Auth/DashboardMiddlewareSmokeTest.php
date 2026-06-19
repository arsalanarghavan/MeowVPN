<?php

namespace Tests\Feature\Auth;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class DashboardMiddlewareSmokeTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_reseller_scope_blocks_foreign_user_detail(): void
    {
        $reseller = DashboardUser::query()->create([
            'username' => 'rs',
            'password' => Hash::make('x'),
            'role' => 'reseller',
            'svp_user_id' => 100,
            'permissions_json' => ['users.manage' => true],
        ]);
        $this->actingAs($reseller);

        $this->getJson('/api/v1/admin/user/99999')
            ->assertForbidden();
    }

    public function test_impersonate_start_requires_admin(): void
    {
        $reseller = DashboardUser::query()->create([
            'username' => 'rs2',
            'password' => Hash::make('x'),
            'role' => 'reseller',
            'svp_user_id' => 100,
        ]);
        $this->actingAs($reseller);

        $this->postJson('/api/v1/admin/impersonate/start', ['user_id' => 101])
            ->assertForbidden();
    }
}
