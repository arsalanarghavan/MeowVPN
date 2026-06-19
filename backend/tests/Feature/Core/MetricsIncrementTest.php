<?php

namespace Tests\Feature\Core;

use App\Support\Metrics\SvpMetrics;
use App\Support\MutateOpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class MetricsIncrementTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['telegram', 'bale', 'xui_panel', 'marketing', 'relay', 'crypto', 'reseller', 'backup', 'l2tp'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    public function test_successful_mutate_increments_mutate_op_total(): void
    {
        $before = SvpMetrics::get('mutate_op_total');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'general',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertGreaterThan($before, SvpMetrics::get('mutate_op_total'));
    }

    /** @return array<string, array{0: string}> */
    public static function catalogOpsProvider(): array
    {
        $out = [];
        foreach (MutateOpCatalog::all() as $op) {
            if (in_array($op, MutateOpCatalog::deprecated(), true)) {
                continue;
            }
            $out[$op] = [$op];
        }

        return $out;
    }

    /** @dataProvider catalogOpsProvider */
    public function test_mutate_op_total_increments_per_catalog_op(string $op): void
    {
        $key = 'mutate_op_total:'.$op;
        $before = SvpMetrics::get($key);

        $response = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ));

        $response->assertOk()->assertJsonPath('ok', true);
        $this->assertGreaterThan($before, SvpMetrics::get($key));
    }
}
