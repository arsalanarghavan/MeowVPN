<?php

namespace Tests\Unit;

use App\Modules\XuiPanel\Services\InboundAutolinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class InboundAutolinkServiceTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_fuzzy_remark_match_links_orphan_service(): void
    {
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => 1,
            'inbound_id' => 5,
            'email' => 'orphan@test.local',
            'remark' => 'VIP User 42',
        ]);
        DB::table('svp_services')->insert([
            'user_id' => 1,
            'panel_id' => 1,
            'email' => 'other@test.local',
            'remark' => 'vip user 42 extra',
            'inbound_id' => 0,
            'created_at' => now(),
        ]);

        $result = app(InboundAutolinkService::class)->run(1, 5);

        $this->assertSame(1, $result['linked']);
        $this->assertSame(5, (int) DB::table('svp_services')->value('inbound_id'));
    }
}
