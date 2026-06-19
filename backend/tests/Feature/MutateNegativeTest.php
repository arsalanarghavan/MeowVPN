<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class MutateNegativeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_unknown_op_returns_error(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'not_a_real_op_xyz',
        ])->assertStatus(422)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'unknown_op');
    }

    public function test_reseller_blocked_on_admin_only_op(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'logs_clear',
        ])->assertOk()
            ->assertJsonPath('ok', false);
    }

    public function test_invalid_settings_tab_rejected(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'not_a_tab',
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_reseller_configs_client_without_perm(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_toggle_enable',
            'client_id' => 1,
            'enabled' => false,
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_relay_mutate_blocked_when_module_off(): void
    {
        $this->setModuleEnabled('relay', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_sync',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_l2tp_mutate_blocked_when_module_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_delete',
            'id' => 1,
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_bot_mutate_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_diagnostics',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_admin_state_l2tp_tab_blocked_when_module_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=l2tp_servers')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_xui_mutate_blocked_when_module_off(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_test',
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_marketing_mutate_blocked_when_module_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_save',
            'segment_key' => 'churned',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_settings_tab_bots_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'bots',
            'telegram_bot_token' => 'x',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
