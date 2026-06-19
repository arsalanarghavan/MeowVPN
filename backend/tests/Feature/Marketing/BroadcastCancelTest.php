<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BroadcastCancelTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_broadcast_cancel_fails_pending_rows(): void
    {
        $bid = DB::table('svp_broadcasts')->insertGetId([
            'owner_svp_user_id' => 0,
            'type' => 'text',
            'content' => '{}',
            'status' => 'sending',
            'created_at' => now(),
        ]);

        DB::table('svp_broadcast_queue')->insert([
            'broadcast_id' => $bid,
            'user_id' => 1,
            'bot' => 'tg',
            'chat_id' => 900001,
            'payload_json' => '{}',
            'status' => 'pending',
            'tries' => 0,
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_cancel',
            'id' => $bid,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_broadcasts', ['id' => $bid, 'status' => 'cancelled']);
        $this->assertDatabaseHas('svp_broadcast_queue', [
            'broadcast_id' => $bid,
            'status' => 'failed',
            'failure_kind' => 'cancelled',
        ]);
    }
}
