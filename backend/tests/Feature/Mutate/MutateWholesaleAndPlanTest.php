<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateWholesaleAndPlanTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_wholesale_line_save_and_assign(): void
    {
        $save = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'wholesale_line_save',
            'panel_id' => 1,
            'label' => 'Line A',
            'price_per_gb' => 1200,
        ])->assertOk()->assertJsonPath('ok', true);

        $lineId = (int) $save->json('data.id');
        $this->assertGreaterThan(0, $lineId);

        $resellerId = (int) DB::table('svp_users')->where('role', 'reseller')->value('id');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_wholesale_lines_assign',
            'reseller_svp_user_id' => $resellerId,
            'line_ids' => [$lineId],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_reseller_wholesale_line_assignments', [
            'reseller_svp_user_id' => $resellerId,
            'line_id' => $lineId,
        ]);
    }

    public function test_plan_category_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'plan_category',
            'label' => 'Acceptance Cat',
            'slug' => 'acceptance-cat',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_plan_categories', ['label' => 'Acceptance Cat']);
    }

    public function test_panel_xp_update(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'id' => 1,
            'label' => 'Updated Panel Label',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_panels', ['id' => 1, 'label' => 'Updated Panel Label']);
    }

    public function test_bot_test_bale_mutate(): void
    {
        Http::fake(['tapi.bale.ai/*' => Http::response(['ok' => true], 200)]);
        app(\App\Services\SettingsStore::class)->set('bale_bot_token', '123:ABC');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_test_bale',
        ])->assertOk();
    }
}
