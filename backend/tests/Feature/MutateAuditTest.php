<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateAuditTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_sensitive_op_writes_audit_log(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_balance_delta',
            'user_id' => 101,
            'delta' => 5,
        ])->assertOk();

        $this->assertDatabaseHas('svp_audit_log', [
            'event_type' => 'user_balance_delta',
            'actor_kind' => 'admin',
        ]);
    }

    public function test_failed_mutate_does_not_audit(): void
    {
        $this->actingAsAdmin();
        $before = DB::table('svp_audit_log')->count();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_balance_delta',
            'user_id' => 999,
            'delta' => 0,
        ]);

        $this->assertSame($before, DB::table('svp_audit_log')->count());
    }

    public function test_reseller_permissions_save_writes_audit_log(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_permissions_save',
            'svp_user_id' => 100,
            'permissions' => ['users.manage' => true],
        ])->assertOk();

        $this->assertDatabaseHas('svp_audit_log', [
            'event_type' => 'reseller_permissions_save',
            'actor_kind' => 'admin',
        ]);
    }

    public function test_user_merge_writes_audit_log(): void
    {
        $this->actingAsAdmin();
        \App\Models\SvpUser::query()->create(['username' => 'a1', 'role' => 'user', 'status' => 'approved']);
        \App\Models\SvpUser::query()->create(['username' => 'a2', 'role' => 'user', 'status' => 'approved']);
        $keep = (int) \App\Models\SvpUser::query()->where('username', 'a1')->value('id');
        $drop = (int) \App\Models\SvpUser::query()->where('username', 'a2')->value('id');

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge',
            'keep_id' => $keep,
            'drop_id' => $drop,
            'confirm' => true,
        ])->assertOk();

        $this->assertDatabaseHas('svp_audit_log', ['event_type' => 'user_merge']);
    }

    public function test_user_manual_create_writes_audit_log(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_manual_create',
            'username' => 'audit_new',
            'first_name' => 'Audit',
        ])->assertOk();

        $this->assertDatabaseHas('svp_audit_log', ['event_type' => 'user_manual_create']);
    }
}
