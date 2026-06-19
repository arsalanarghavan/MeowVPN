<?php

namespace Tests\Feature\Xui;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 B.6.1 — purge-expired ready list with seeded service (v16). */
class PurgeExpiredReadyListTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        app(SettingsStore::class)->set('purge_expired_grace_days', 3);
    }

    public function test_purge_expired_ready_list_includes_seeded_service(): void
    {
        DB::table('svp_services')->insert([
            'user_id' => 101,
            'panel_id' => 1,
            'inbound_id' => 1,
            'service_type' => 'xray',
            'email' => 'expired@local',
            'expires_at' => now()->subDays(10),
            'total_traffic' => 0,
            'used_traffic' => 0,
            'created_at' => now(),
        ]);

        $r = $this->actingAsAdmin()->getJson('/api/v1/admin/purge-expired?status=ready');
        $r->assertOk()->assertJsonPath('ok', true);
        $ids = collect($r->json('items'))->pluck('id')->map(fn ($v) => (int) $v)->all();
        $this->assertNotEmpty($ids);
        $this->assertGreaterThan(0, (int) $r->json('totals.ready'));
    }
}
