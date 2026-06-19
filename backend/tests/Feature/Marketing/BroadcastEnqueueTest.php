<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BroadcastEnqueueTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_broadcast_send_creates_queue_rows_and_total_targets(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_send',
            'bc_text' => '<b>Hello</b> everyone',
            'bc_targets' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        $broadcast = DB::table('svp_broadcasts')->orderByDesc('id')->first();
        $this->assertNotNull($broadcast);
        $this->assertSame('sending', $broadcast->status);
        $this->assertGreaterThan(0, (int) $broadcast->total_targets);

        $queueCount = DB::table('svp_broadcast_queue')
            ->where('broadcast_id', $broadcast->id)
            ->count();
        $this->assertGreaterThan(0, $queueCount);
    }
}
