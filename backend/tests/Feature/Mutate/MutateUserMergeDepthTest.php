<?php

namespace Tests\Feature\Mutate;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** user_merge depth in Feature/Mutate with cascade assertions (v13). */
class MutateUserMergeDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_user_merge_moves_transactions_and_services(): void
    {
        $this->actingAsAdmin();

        SvpUser::query()->create(['username' => 'merge_src', 'role' => 'user', 'status' => 'approved', 'balance' => 10]);
        SvpUser::query()->create(['username' => 'merge_dst', 'role' => 'user', 'status' => 'approved', 'balance' => 20]);
        $src = (int) SvpUser::query()->where('username', 'merge_src')->value('id');
        $dst = (int) SvpUser::query()->where('username', 'merge_dst')->value('id');

        DB::table('svp_services')->insert([
            'user_id' => $src,
            'service_type' => 'xray',
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'merge-src@svp.local',
            'created_at' => now(),
        ]);
        DB::table('svp_transactions')->insert([
            'user_id' => $src,
            'type' => 'purchase',
            'amount' => 5000,
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge',
            'keep_id' => $dst,
            'drop_id' => $src,
            'confirm' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertNull(SvpUser::query()->find($src));
        $this->assertSame($dst, (int) DB::table('svp_services')->value('user_id'));
        $this->assertSame($dst, (int) DB::table('svp_transactions')->value('user_id'));
        $this->assertDatabaseHas('svp_audit_log', ['event_type' => 'user_merge']);
    }
}
