<?php

namespace Tests\Feature\Core;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §14 A.2.2 externalHostSnapshots (v15). */
class MonitorHostSnapshotsTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_monitoring_refresh_populates_external_host_snapshots(): void
    {
        DB::table('svp_monitor_hosts')->insert([
            'label' => 'Prom Host',
            'metrics_url' => 'https://metrics.test/api/v1/query',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);

        Http::fake([
            'https://metrics.test/*' => Http::response(['status' => 'success'], 200),
        ]);

        $overview = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=monitoring&refreshLivePanelMetrics=1')
            ->assertOk()
            ->json('overview');

        $snaps = $overview['externalHostSnapshots'] ?? [];
        $this->assertIsArray($snaps);
        $this->assertNotEmpty($snaps);
        $this->assertTrue($snaps[0]['ok'] ?? false);
    }
}
