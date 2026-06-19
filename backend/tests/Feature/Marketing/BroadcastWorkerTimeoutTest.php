<?php

namespace Tests\Feature\Marketing;

use App\Modules\Marketing\Services\BroadcastWorkerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §16 P9 — broadcast worker at 1000+ targets with reclaim timeout (v22). */
class BroadcastWorkerTimeoutTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]], 200)]);
    }

    public function test_worker_processes_large_queue_without_stuck_sending(): void
    {
        $bid = DB::table('svp_broadcasts')->insertGetId([
            'owner_svp_user_id' => 0,
            'type' => 'text',
            'content' => json_encode(['text' => 'scale', 'parse_mode' => 'HTML']),
            'status' => 'sending',
            'total_targets' => 1000,
            'created_at' => now(),
        ]);

        for ($i = 1; $i <= 1000; $i++) {
            DB::table('svp_broadcast_queue')->insert([
                'broadcast_id' => $bid,
                'user_id' => 1000 + $i,
                'bot' => 'tg',
                'chat_id' => 700000000 + $i,
                'payload_json' => json_encode(['chat_id' => 700000000 + $i, 'text' => 'scale', 'parse_mode' => 'HTML']),
                'status' => 'pending',
                'tries' => 0,
                'updated_at' => now(),
            ]);
        }

        DB::table('svp_settings')->updateOrInsert(
            ['key_name' => 'broadcast_sending_timeout_sec'],
            ['value' => '120', 'updated_at' => now()]
        );

        $worker = app(BroadcastWorkerService::class);
        $sent = 0;
        for ($round = 0; $round < 60 && $sent < 1000; $round++) {
            $worker->runBatch();
            $sent = (int) DB::table('svp_broadcast_queue')
                ->where('broadcast_id', $bid)
                ->where('status', 'sent')
                ->count();
        }

        $this->assertGreaterThanOrEqual(20, $sent);
        $stuck = (int) DB::table('svp_broadcast_queue')
            ->where('broadcast_id', $bid)
            ->where('status', 'sending')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->count();
        $this->assertSame(0, $stuck);
    }
}
