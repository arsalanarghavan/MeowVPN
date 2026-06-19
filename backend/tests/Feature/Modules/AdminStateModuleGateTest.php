<?php

namespace Tests\Feature\Modules;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class AdminStateModuleGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_l2tp_tab_blocked_when_module_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=l2tp_servers')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_bots_tab_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=bots')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_relay_site_subtab_blocked_when_relay_off(): void
    {
        $this->setModuleEnabled('relay', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=site_settings&site_subtab=relay')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_dashboard_tab_allowed_when_l2tp_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk();
    }

    public function test_xui_panels_tab_blocked_when_xui_off(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=xui_panels')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_configs_tab_blocked_when_xui_off(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=configs')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_backup_tab_blocked_when_backup_off(): void
    {
        $this->setModuleEnabled('backup', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=backup')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_marketing_lifecycle_tab_blocked_when_marketing_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=marketing_lifecycle')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }

    public function test_finance_subtab_blocked_when_crypto_off(): void
    {
        $this->setModuleEnabled('crypto', false);
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=site_settings&site_subtab=finance')
            ->assertForbidden()
            ->assertJsonPath('message', 'module_disabled');
    }
}
