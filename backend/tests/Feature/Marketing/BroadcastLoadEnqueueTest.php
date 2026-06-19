<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §16 P9 — broadcast enqueue at scale (smoke). */
class BroadcastLoadEnqueueTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_broadcast_enqueue_100_targets(): void
    {
        for ($i = 1; $i <= 100; $i++) {
            DB::table('svp_users')->insert([
                'id' => 1000 + $i,
                'username' => 'load_u_'.$i,
                'role' => 'user',
                'status' => 'approved',
                'tg_user_id' => 900000000 + $i,
                'created_at' => now(),
            ]);
        }

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_send',
            'bc_text' => 'load test',
            'bc_targets' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        $broadcast = DB::table('svp_broadcasts')->orderByDesc('id')->first();
        $this->assertNotNull($broadcast);
        $this->assertGreaterThanOrEqual(100, (int) $broadcast->total_targets);

        $queued = DB::table('svp_broadcast_queue')->where('broadcast_id', $broadcast->id)->count();
        $this->assertGreaterThanOrEqual(100, $queued);
    }

    public function test_broadcast_enqueue_1000_targets(): void
    {
        $base = 2000;
        for ($i = 1; $i <= 1000; $i++) {
            DB::table('svp_users')->insert([
                'id' => $base + $i,
                'username' => 'load1k_'.$i,
                'role' => 'user',
                'status' => 'approved',
                'tg_user_id' => 800000000 + $i,
                'created_at' => now(),
            ]);
        }

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_send',
            'bc_text' => 'load 1k',
            'bc_targets' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        $broadcast = DB::table('svp_broadcasts')->orderByDesc('id')->first();
        $this->assertNotNull($broadcast);
        $this->assertGreaterThanOrEqual(1000, (int) $broadcast->total_targets);
    }
}
