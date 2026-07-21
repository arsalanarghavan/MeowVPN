<?php

namespace Tests\Feature\Mutate;

use App\Modules\Commerce\Services\PlanResellerRulesService;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class PlanResellerCatalogDefaultsTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('reseller', true);
    }

    public function test_merge_defaults_uses_catalog_when_panel_row_disallows_use(): void
    {
        DB::table('svp_reseller_panel_prices')->updateOrInsert(
            ['reseller_svp_user_id' => 100, 'panel_id' => 1],
            [
                'price_per_gb' => 0,
                'panel_access' => 0,
                'default_service_type' => 'xray',
                'default_inbound_id' => 99,
                'default_l2tp_server_id' => 0,
                'updated_at' => now(),
            ]
        );

        $lineId = (int) DB::table('svp_reseller_wholesale_lines')->insertGetId([
            'panel_id' => 1,
            'inbound_id' => 7,
            'label' => 'Catalog L2TP',
            'price_per_gb' => 100,
            'default_service_type' => 'l2tp',
            'default_inbound_id' => 0,
            'default_l2tp_server_id' => 3,
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('svp_reseller_wholesale_line_assignments')->insert([
            'reseller_svp_user_id' => 100,
            'line_id' => $lineId,
            'created_at' => now(),
        ]);

        $merged = app(PlanResellerRulesService::class)->mergeResellerPlanDefaults(100, [
            'panel_id' => 1,
            'inbound_id' => 5,
            'inbound_ids' => json_encode([5]),
            'service_type' => 'xray',
        ]);

        $this->assertSame('l2tp', $merged['service_type']);
        $this->assertSame(0, (int) ($merged['inbound_id'] ?? -1));
        $this->assertNull($merged['inbound_ids'] ?? null);
        $this->assertSame(3, (int) ($merged['l2tp_server_id'] ?? 0));
    }
}
