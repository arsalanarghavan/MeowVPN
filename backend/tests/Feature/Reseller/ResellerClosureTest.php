<?php

namespace Tests\Feature\Reseller;

use App\Models\SvpUser;
use App\Modules\Reseller\Services\ResellerClosureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerClosureTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_rebuild_all_builds_downline_rows(): void
    {
        $closure = app(ResellerClosureService::class);
        DB::table('svp_reseller_closure')->truncate();
        $closure->rebuildAll();

        $this->assertDatabaseHas('svp_reseller_closure', [
            'ancestor_id' => 100,
            'descendant_id' => 100,
            'depth' => 0,
        ]);
        $this->assertDatabaseHas('svp_reseller_closure', [
            'ancestor_id' => 100,
            'descendant_id' => 101,
            'depth' => 1,
        ]);
    }

    public function test_user_set_referrer_updates_closure(): void
    {
        $grandchild = SvpUser::query()->create([
            'username' => 'gc1',
            'role' => 'user',
            'status' => 'approved',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_set_referrer',
            'user_id' => $grandchild->id,
            'invited_by' => 101,
        ])->assertOk();

        $this->assertDatabaseHas('svp_reseller_closure', [
            'ancestor_id' => 100,
            'descendant_id' => $grandchild->id,
            'depth' => 2,
        ]);
    }

    public function test_referral_cycle_is_rejected(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_set_referrer',
            'user_id' => 100,
            'invited_by' => 101,
        ])->assertStatus(422)->assertJson(['ok' => false, 'message' => 'referral_cycle']);
    }

    public function test_descendant_ids_for_ancestor(): void
    {
        $ids = app(ResellerClosureService::class)->descendantIdsForAncestor(100);

        $this->assertContains(100, $ids);
        $this->assertContains(101, $ids);
    }
}
