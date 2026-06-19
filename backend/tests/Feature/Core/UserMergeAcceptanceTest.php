<?php

namespace Tests\Feature\Core;

use App\Models\SvpUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 C.4 — user merge */
class UserMergeAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_user_merge_preview_returns_diff(): void
    {
        $this->actingAsAdmin();
        SvpUser::query()->create(['username' => 'merge_a', 'role' => 'user', 'status' => 'approved', 'balance' => 100]);
        SvpUser::query()->create(['username' => 'merge_b', 'role' => 'user', 'status' => 'approved', 'balance' => 50]);
        $a = (int) SvpUser::query()->where('username', 'merge_a')->value('id');
        $b = (int) SvpUser::query()->where('username', 'merge_b')->value('id');

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge_preview',
            'keep_id' => $a,
            'drop_id' => $b,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_user_merge_atomic_leaves_one_user(): void
    {
        $this->actingAsAdmin();
        SvpUser::query()->create(['username' => 'src_u', 'role' => 'user', 'status' => 'approved']);
        SvpUser::query()->create(['username' => 'dst_u', 'role' => 'user', 'status' => 'approved']);
        $src = (int) SvpUser::query()->where('username', 'src_u')->value('id');
        $dst = (int) SvpUser::query()->where('username', 'dst_u')->value('id');
        DB::table('svp_services')->insert([
            'user_id' => $src,
            'service_type' => 'xray',
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'src@svp.local',
            'created_at' => now(),
        ]);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_merge',
            'keep_id' => $dst,
            'drop_id' => $src,
            'confirm' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertNull(SvpUser::query()->find($src));
        $this->assertNotNull(SvpUser::query()->find($dst));
        $this->assertSame($dst, (int) DB::table('svp_services')->value('user_id'));
    }
}
