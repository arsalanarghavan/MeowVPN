<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateResellerAdminDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_bind_users(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_bind_users',
            'reseller_svp_user_id' => 100,
            'user_ids' => [101],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_users', [
            'id' => 101,
            'signup_reseller_svp_id' => 100,
        ]);
    }

    public function test_reseller_inbound_labels_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_inbound_labels_save',
            'reseller_svp_user_id' => 100,
            'labels' => [
                ['panel_id' => 1, 'inbound_id' => 1, 'label' => 'VIP Line'],
            ],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_reseller_inbound_display_names', [
            'reseller_svp_user_id' => 100,
            'panel_id' => 1,
            'inbound_id' => 1,
            'label' => 'VIP Line',
        ]);
    }

    public function test_reseller_payment_methods_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_payment_methods_save',
            'reseller_svp_user_id' => 100,
            'methods' => ['c2c' => true, 'crypto' => false],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_wholesale_line_delete(): void
    {
        $lineId = (int) DB::table('svp_reseller_wholesale_lines')->insertGetId([
            'panel_id' => 1,
            'inbound_id' => 1,
            'label' => 'temp line',
            'price_per_gb' => 1000,
            'active' => true,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'wholesale_line_delete',
            'id' => $lineId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_reseller_wholesale_lines', ['id' => $lineId]);
    }

    public function test_reseller_backfill_run(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_backfill_run',
            'limit' => 10,
        ])->assertOk()->assertJsonPath('ok', true)
            ->assertJsonStructure(['processed']);
    }

    public function test_bot_reseller_secret_rotate(): void
    {
        $old = (string) DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', 100)
            ->value('webhook_secret');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_reseller_secret_rotate',
            'reseller_svp_user_id' => 100,
        ])->assertOk()->assertJsonPath('ok', true);

        $new = (string) DB::table('svp_reseller_bot_profiles')
            ->where('reseller_svp_user_id', 100)
            ->value('webhook_secret');
        $this->assertNotSame($old, $new);
    }

    public function test_reseller_permissions_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_permissions_save',
            'svp_user_id' => 100,
            'permissions' => ['users.manage' => true, 'plans.manage' => true],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_reseller_wallet_topup_checkout(): void
    {
        $reseller = \App\Models\DashboardUser::query()->where('username', 'reseller')->first();
        $this->actingAs($reseller)->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_wallet_topup_checkout',
            'amount' => 25000,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_reseller_dashboard_provision(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_dashboard_provision',
            'username' => 'v12reseller',
            'password' => 'secret-pass',
            'parent_svp_user_id' => 100,
            'permissions' => ['users.manage' => true],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_users', ['username' => 'v12reseller', 'role' => 'reseller']);
        $this->assertDatabaseHas('dashboard_users', ['username' => 'v12reseller', 'role' => 'reseller']);
    }
}
