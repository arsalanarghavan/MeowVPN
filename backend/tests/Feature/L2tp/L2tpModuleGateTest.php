<?php

namespace Tests\Feature\L2tp;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** §16 P10 — L2TP tab با feature flag (v23). */
class L2tpModuleGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_l2tp_tab_hidden_when_module_disabled(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $tabs = collect($this->actingAsAdmin()->getJson('/api/v1/bootstrap')->json('navTabs') ?? [])
            ->pluck('key')->all();
        $this->assertNotContains('l2tp_servers', $tabs);
    }

    public function test_l2tp_tab_visible_when_module_enabled(): void
    {
        $this->setModuleEnabled('l2tp', true);
        $tabs = collect($this->actingAsAdmin()->getJson('/api/v1/bootstrap')->json('navTabs') ?? [])
            ->pluck('key')->all();
        $this->assertContains('l2tp_servers', $tabs);
    }
}
