<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v21 part 1 (15 ops). */
class MutateDepthBatchV21Part1Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['marketing', 'xui_panel', 'relay', 'crypto'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    /** @return array<string, array{0: string}> */
    public static function depthOpsProvider(): array
    {
        $ops = [
            'wholesale_line_save', 'broadcast_cancel', 'panel_economics_mark_paid', 'reseller_backfill_run',
            'force_join_publish', 'discount_save', 'discount_delete', 'card_reorder', 'plan_category',
            'panel_xp', 'inbound_link', 'inbound_autolink', 'purge_expired_run_cron', 'logs_clear',
            'receipt_reject_reasons_save',
        ];
        $out = [];
        foreach ($ops as $op) {
            $out[$op] = [$op];
        }

        return $out;
    }

    /** @dataProvider depthOpsProvider */
    public function test_admin_depth_op_ok(string $op): void
    {
        if ($op === 'force_join_publish') {
            app(SettingsStore::class)->merge(['force_join_enabled' => true, 'force_join_channel' => '@test']);
        }
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('ok', true);
    }
}
