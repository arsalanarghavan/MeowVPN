<?php

namespace Tests\Unit;

use App\Services\NavTabsBuilder;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class NavTabsBuilderTest extends TestCase
{
    use TogglesModules;

    public function test_admin_nav_includes_spec_tabs(): void
    {
        $this->setModuleEnabled('xui_panel', true);
        $this->setModuleEnabled('reseller', true);
        $this->setModuleEnabled('telegram', true);

        $keys = array_column(app(NavTabsBuilder::class)->build(true), 'key');

        foreach (['users_bulk', 'bot_ui', 'unit_economics', 'reseller_charge', 'reseller_settings', 'reseller_xui_panels', 'cards'] as $tab) {
            $this->assertContains($tab, $keys, "Missing nav tab: {$tab}");
        }
    }

    public function test_cards_tab_visible_when_crypto_module_off(): void
    {
        $this->setModuleEnabled('crypto', false);
        $this->setModuleEnabled('xui_panel', true);
        $keys = array_column(app(NavTabsBuilder::class)->build(true), 'key');
        $this->assertContains('cards', $keys);
    }

    public function test_broadcast_tab_hidden_when_marketing_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $keys = array_column(app(NavTabsBuilder::class)->build(true), 'key');
        $this->assertNotContains('broadcast', $keys);
    }

    public function test_marketing_lifecycle_tab_hidden_when_marketing_off(): void
    {
        $this->setModuleEnabled('marketing', false);
        $keys = array_column(app(NavTabsBuilder::class)->build(true), 'key');
        $this->assertNotContains('marketing_lifecycle', $keys);
    }
}
