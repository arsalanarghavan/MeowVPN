<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateReceiptDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_receipt_set_status_reject(): void
    {
        $id = (int) DB::table('svp_receipts')->min('id');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_set_status',
            'receipt_id' => $id,
            'status' => 'rejected',
            'reject_reason' => 'test',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_receipts', ['id' => $id, 'status' => 'rejected']);
    }

    public function test_receipt_update_amount(): void
    {
        $id = (int) DB::table('svp_receipts')->min('id');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_update',
            'receipt_id' => $id,
            'amount' => 99000,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_receipt_reject_reasons_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_reject_reasons_save',
            'reasons' => ['amount_mismatch', 'blurry_photo'],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_discount_redemptions_list(): void
    {
        DB::table('svp_discount_redemptions')->insert([
            'discount_id' => 1,
            'user_id' => 101,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'discount_redemptions',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
