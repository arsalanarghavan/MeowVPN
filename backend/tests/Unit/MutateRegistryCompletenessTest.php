<?php

namespace Tests\Unit;

use App\Services\MutationRegistry;
use App\Support\MutateOpCatalog;
use Tests\TestCase;

class MutateRegistryCompletenessTest extends TestCase
{
    public function test_all_canonical_ops_registered(): void
    {
        $registry = app(MutationRegistry::class);
        $all = $registry->all();
        $canonical = MutateOpCatalog::all();

        $this->assertCount(164, $canonical);
        $this->assertSame(count($canonical), count(array_unique($canonical)), 'Duplicate ops in catalog');

        foreach ($canonical as $op) {
            if (str_starts_with($op, 'telegram_relay_') && ! svp_modules()->isEnabled('relay')) {
                continue;
            }
            if (str_starts_with($op, 'l2tp_') && ! svp_modules()->isEnabled('l2tp')) {
                continue;
            }
            $this->assertTrue($registry->has($op), "Missing op: {$op}");
        }

        $this->assertTrue($registry->has('panel_merge_preview'));
        $this->assertTrue($registry->has('panel_merge_execute'));
        $this->assertTrue($registry->has('configs_panel_del_orphans'));
        $this->assertTrue($registry->has('configs_bulk_reset_traffic'));
        $this->assertTrue($registry->has('configs_panel_del_depleted'));
        $this->assertTrue($registry->has('configs_client_fetch_ips'));
    }
}
