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

        $this->assertCount(139, $canonical);

        foreach ($canonical as $op) {
            $this->assertTrue($registry->has($op), "Missing op: {$op}");
        }

        $this->assertCount(139, array_keys($all), 'Duplicate or extra ops in registry');
    }
}
