<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** l2tp_add depth in Feature/Mutate (v13 §15 file parity). */
class MutateL2tpParityTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('l2tp', true);
    }

    public function test_l2tp_add_creates_server_row(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_add',
            'label' => 'Edge L2TP',
            'ssh_host' => '10.0.0.5',
            'l2tp_host' => 'l2tp.edge.test',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_l2tp_servers', [
            'label' => 'Edge L2TP',
            'ssh_host' => '10.0.0.5',
            'l2tp_host' => 'l2tp.edge.test',
        ]);
    }

    public function test_l2tp_add_returns_id(): void
    {
        $before = (int) DB::table('svp_l2tp_servers')->count();

        $res = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_add',
            'label' => 'L2TP Parity',
            'ssh_host' => '10.0.0.6',
            'l2tp_host' => 'l2tp.parity.test',
        ])->assertOk()->json();

        $this->assertTrue($res['ok'] ?? false);
        $this->assertSame($before + 1, (int) DB::table('svp_l2tp_servers')->count());
    }
}
