<?php

namespace Tests\Feature\Marketing;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BroadcastQueueApiTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_admin_can_fetch_broadcast_queue_page(): void
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

        $this->actingAsAdmin()
            ->getJson("/api/v1/admin/broadcast-queue?broadcast_id={$bid}&page=1&per_page=25")
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['pagination' => ['page', 'perPage', 'total'], 'users']);
    }
}
