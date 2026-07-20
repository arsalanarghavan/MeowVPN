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

    /** Ops that cannot return ok:true with default smoke fixtures. */
    private const SKIP_METRIC_OPS = [
        'panel_merge_execute',
        'configs_panel_del_orphans',
        'configs_bulk_reset_traffic',
        'configs_reset_all_panel_traffic',
        'configs_panel_del_depleted',
        'configs_client_fetch_ips',
        'configs_client_clear_ips',
        'configs_client_set_inbounds',
        'configs_clients_bulk_set_inbounds',
        'configs_inbound_patch',
        'configs_delete_expired_older_than',
    ];

    /** @return array<string, array{0: string}> */
    public static function catalogOpsProvider(): array
    {
        $out = [];
        foreach (MutateOpCatalog::all() as $op) {
            if (in_array($op, MutateOpCatalog::deprecated(), true)) {
                continue;
            }
            if (in_array($op, self::SKIP_METRIC_OPS, true)) {
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
