<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v20 part 1 (wholesale, broadcast, panel economics). */
class MutateDepthBatchV20Part1Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('marketing', true);
        $this->setModuleEnabled('xui_panel', true);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    public function test_wholesale_line_save_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'wholesale_line_save',
            'name' => 'Line v20',
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_broadcast_cancel_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_cancel',
            'broadcast_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_panel_economics_mark_paid_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_economics_mark_paid',
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_reseller_backfill_run_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_backfill_run',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_force_join_publish_mutate(): void
    {
        app(SettingsStore::class)->merge(['force_join_enabled' => true, 'force_join_channel' => '@test']);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
