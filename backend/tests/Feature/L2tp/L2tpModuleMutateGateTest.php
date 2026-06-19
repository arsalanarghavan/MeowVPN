<?php

namespace Tests\Feature\L2tp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class L2tpModuleMutateGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('l2tp', false);
    }

    public function test_l2tp_mutate_blocked_when_module_off(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_add',
            'label' => 'X',
            'ssh_host' => '10.0.0.1',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
