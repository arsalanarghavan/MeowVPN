<?php

namespace Tests\Feature\Mutate;

use App\Support\MutateOpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth smoke — batch 1 (v18). */
class MutateDepthBatchV18Part1Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('telegram', true);
        $this->setModuleEnabled('bale', true);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_bot_toggle_enabled_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_toggle_enabled',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_bot_toggle_platform_enabled_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_toggle_platform_enabled',
            'platform' => 'telegram',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_receipt_reject_reasons_save_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_reject_reasons_save',
            'reasons' => ['bad image', 'wrong amount'],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_purge_expired_run_cron_mutate(): void
    {
        $this->setModuleEnabled('xui_panel', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_run_cron',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
