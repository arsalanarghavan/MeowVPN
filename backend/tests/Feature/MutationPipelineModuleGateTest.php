<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Regression for MutationPipeline + EnsureAdminStateModule gates (v12). */
class MutationPipelineModuleGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_xui_panels_in_bootstrap_when_services_manage(): void
    {
        $boot = $this->actingAsReseller()->getJson('/api/v1/bootstrap')->assertOk()->json();
        $tabs = collect($boot['navTabs'] ?? [])->pluck('key')->all();
        $this->assertContains('reseller_xui_panels', $tabs);
    }

    public function test_unit_economics_tab_blocked_when_xui_off(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=unit_economics')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_broadcast_tab_blocked_when_marketing_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=broadcast')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_broadcast_send_blocked_when_marketing_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'broadcast_send',
            'bc_text' => 'hi',
            'bc_targets' => 'telegram',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_configs_sync_endpoint_blocked_when_xui_off(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/configs-sync', [
            'panel_id' => 1,
        ])->assertForbidden();
    }

    public function test_finance_settings_tab_blocked_when_crypto_off(): void
    {
        $this->setModuleEnabled('crypto', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'finance',
            'crypto_enabled' => true,
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
