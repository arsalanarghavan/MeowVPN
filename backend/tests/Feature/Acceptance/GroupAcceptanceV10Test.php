<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 v10 — remaining acceptance gaps (backend-evidenced). */
class GroupAcceptanceV10Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_customer_charges_list_in_state(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/state?tab=reseller_charge')
            ->assertOk()
            ->assertJsonStructure([
                'resellerCustomerCharges',
                'resellerCustomerChargesPagination',
            ]);
    }

    public function test_reseller_plan_floors_key_in_plans_state(): void
    {
        $json = $this->actingAsReseller()->getJson('/api/v1/admin/state?tab=plans')->assertOk()->json();
        $this->assertArrayHasKey('resellerPlanFloors', $json);
    }

    public function test_whitelabel_branding_keys_in_state(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=site_settings&site_subtab=whitelabel')
            ->assertOk()
            ->json();
        $this->assertArrayHasKey('settings', $json);
        $this->assertArrayHasKey('branding', $json);
    }

    public function test_reseller_nav_tabs_non_empty(): void
    {
        $boot = $this->actingAsReseller()->getJson('/api/v1/bootstrap')->assertOk()->json();
        $tabs = $boot['navTabs'] ?? [];
        $this->assertIsArray($tabs);
        $this->assertNotEmpty($tabs);
        $keys = array_column($tabs, 'key');
        $this->assertContains('users', $keys);
    }
}
