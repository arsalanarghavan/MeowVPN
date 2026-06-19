<?php

namespace Tests\Feature\Reseller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerBackfillTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_backfill_run_returns_counts(): void
    {
        DB::table('svp_transactions')->insert([
            'user_id' => 101,
            'amount' => 1000,
            'type' => 'purchase',
            'status' => 'approved',
            'meta_json' => '{}',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_backfill_run',
            'limit' => 50,
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['processed', 'billing', 'invited_by']);
    }
}
