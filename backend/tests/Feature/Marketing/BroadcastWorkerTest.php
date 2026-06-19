<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Services\BroadcastWorkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class BroadcastWorkerTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_worker_marks_queue_item_sent_and_increments_counter(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);

        $bid = DB::table('svp_broadcasts')->insertGetId([
            'owner_svp_user_id' => 0,
            'type' => 'text',
            'content' => json_encode(['text' => 'hi', 'parse_mode' => 'HTML']),
            'status' => 'sending',
            'total_targets' => 1,
            'created_at' => now(),
        ]);

        DB::table('svp_broadcast_queue')->insert([
            'broadcast_id' => $bid,
            'user_id' => 1,
            'bot' => 'tg',
            'chat_id' => 900001,
            'payload_json' => json_encode(['chat_id' => 900001, 'text' => 'hi', 'parse_mode' => 'HTML']),
            'status' => 'pending',
            'tries' => 0,
            'updated_at' => now(),
        ]);

        app(BroadcastWorkerService::class)->runBatch();

        $this->assertDatabaseHas('svp_broadcast_queue', [
            'broadcast_id' => $bid,
            'status' => 'sent',
        ]);

        $broadcast = DB::table('svp_broadcasts')->where('id', $bid)->first();
        $this->assertSame(1, (int) $broadcast->sent_count);
    }
}
