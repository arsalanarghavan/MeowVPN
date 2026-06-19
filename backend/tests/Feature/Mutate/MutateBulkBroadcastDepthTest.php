<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateBulkBroadcastDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_users_bulk_extend_alerts_slots(): void
    {
        foreach (['users_bulk_extend', 'users_bulk_alerts', 'users_bulk_slots'] as $op) {
            $payload = match ($op) {
                'users_bulk_extend' => ['days' => 3, 'scope' => 'all_approved'],
                'users_bulk_alerts' => ['alerts' => ['traffic' => true], 'scope' => 'all_approved'],
                'users_bulk_slots' => ['extra_users' => 1, 'scope' => 'all_approved'],
            };
            $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(['op' => $op], $payload))
                ->assertOk()
                ->assertJsonPath('ok', true);
        }
    }

    public function test_users_bulk_run_worker(): void
    {
        DB::table('svp_users_bulk_jobs')->insert([
            'operation' => 'extend',
            'scope' => 'all_approved',
            'payload_json' => json_encode(['days' => 1]),
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_run_worker',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_broadcast_run_worker(): void
    {
        $bcId = (int) DB::table('svp_broadcasts')->insertGetId([
            'owner_svp_user_id' => 0,
            'type' => 'text',
            'content' => 'test',
            'status' => 'queued',
            'created_at' => now(),
        ]);
        DB::table('svp_broadcast_queue')->insert([
            'broadcast_id' => $bcId,
            'user_id' => 101,
            'bot' => 'telegram',
            'chat_id' => 12345,
            'payload_json' => json_encode(['text' => 'hi']),
            'status' => 'pending',
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_run_worker',
            'max_iterations' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_broadcast_send_creates_queue_and_broadcast_cancel(): void
    {
        $send = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_send',
            'bc_text' => 'depth v12',
            'bc_targets' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);

        $bcId = (int) DB::table('svp_broadcasts')->orderByDesc('id')->value('id');
        $this->assertGreaterThan(0, DB::table('svp_broadcast_queue')->where('broadcast_id', $bcId)->count());

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_cancel',
            'id' => $bcId,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertSame('cancelled', DB::table('svp_broadcasts')->where('id', $bcId)->value('status'));
    }

    public function test_crypto_settings_encrypts_api_key(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'crypto_settings',
            'crypto_nowpayments_api_key' => 'np-v12-key',
        ])->assertOk()->assertJsonPath('ok', true);

        $stored = (string) DB::table('svp_settings')->where('key_name', 'crypto_nowpayments_api_key')->value('value');
        $this->assertNotSame('np-v12-key', $stored);
    }

    public function test_discount_save_and_delete(): void
    {
        $save = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'discount_save',
            'code' => 'V12OFF',
            'percent' => 15,
            'active' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $id = (int) $save->json('id');
        $this->assertDatabaseHas('svp_discount_codes', ['id' => $id, 'code' => 'V12OFF']);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'discount_delete',
            'id' => $id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_discount_codes', ['id' => $id]);
    }
}
