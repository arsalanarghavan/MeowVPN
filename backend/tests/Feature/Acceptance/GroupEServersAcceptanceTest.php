<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group E — Servers / XUI */
class GroupEServersAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_panel_test_mutate(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', ['op' => 'panel_test', 'panel_id' => 1])
            ->assertOk();
    }

    public function test_inbound_display_catalog(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/inbound-display-catalog')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_panel_inbound_map_get(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/panel/inbound-map?panel_id=1')
            ->assertOk();
    }

    public function test_configs_snapshot(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/configs-snapshot?panel_id=1')
            ->assertOk();
    }

    public function test_panels_tab_in_state(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=xui_panels')
            ->assertOk()
            ->assertJsonStructure(['panels', 'pagination']);
    }

    public function test_panel_xp_create_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'label' => 'Acceptance Panel',
            'panel_url' => 'https://panel.acceptance.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_panels', ['label' => 'Acceptance Panel']);
    }
}
