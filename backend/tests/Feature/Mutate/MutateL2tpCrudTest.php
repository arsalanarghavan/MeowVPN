<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class MutateL2tpCrudTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('l2tp', true);
    }

    public function test_l2tp_update_and_delete(): void
    {
        $id = (int) DB::table('svp_l2tp_servers')->insertGetId([
            'label' => 'L2TP Test',
            'ssh_host' => '10.0.0.2',
            'l2tp_host' => 'l2tp.example.com',
            'active' => true,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_update',
            'id' => $id,
            'label' => 'L2TP Updated',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_l2tp_servers', ['id' => $id, 'label' => 'L2TP Updated']);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_delete',
            'id' => $id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_l2tp_servers', ['id' => $id]);
    }
}
