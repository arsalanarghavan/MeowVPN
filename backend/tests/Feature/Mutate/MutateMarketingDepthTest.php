<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateMarketingDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_marketing_rule_save_delete_run_now(): void
    {
        $save = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_save',
            'name' => 'Depth rule',
            'segment_key' => 'never_purchased',
            'enabled' => true,
            'cooldown_days' => 7,
        ])->assertOk()->assertJsonPath('ok', true);

        $ruleId = (int) ($save->json('data.id') ?? 0);
        if ($ruleId < 1) {
            $ruleId = (int) DB::table('svp_marketing_rules')->max('id');
        }

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_run_rule_now',
            'rule_id' => $ruleId,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_send_manual',
            'segment_key' => 'never_purchased',
            'message' => 'hello',
        ])->assertOk();

        if ($ruleId > 0) {
            $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
                'op' => 'marketing_rule_delete',
                'rule_id' => $ruleId,
            ])->assertOk()->assertJsonPath('ok', true);
        }
    }

    public function test_users_bulk_wallet_enqueue(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'users_bulk_wallet',
            'scope' => 'custom_ids',
            'user_ids' => [101],
            'delta' => 10,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_users_bulk_jobs', ['operation' => 'wallet']);
    }
}
