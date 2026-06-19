<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 v9 — quick links, economics, settings panel alias */
class GroupAcceptanceV9Test extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->createSvpTestSchema();
    }

    public function test_reseller_boot_includes_allowed_nav_tabs(): void
    {
        $dash = DashboardUser::query()->where('username', 'reseller')->first();
        $dash->permissions_json = [
            'users.manage' => true,
            'plans.manage' => true,
            'receipts.review' => true,
        ];
        $dash->save();

        $boot = $this->actingAs($dash)->getJson('/api/v1/bootstrap')->assertOk()->json();
        $tabs = collect($boot['navTabs'] ?? [])->pluck('key')->all();
        $this->assertContains('users', $tabs);
        $this->assertContains('receipts', $tabs);
        $this->assertNotContains('audit', $tabs);
    }

    public function test_dashboard_state_includes_overview_and_stats(): void
    {
        $this->actingAsAdmin();
        $overview = $this->getJson('/api/v1/admin/state?tab=dashboard')->assertOk()->json('overview');
        $this->assertIsArray($overview);
        $this->assertArrayHasKey('users_total', $overview);
        $this->assertArrayHasKey('panels_total', $overview);
    }

    public function test_settings_tab_panel_alias_maps_to_logs(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'panel',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_impersonation_audit_event_filterable(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();

        $this->getJson('/api/v1/admin/audit?event_type=impersonation.start&limit=5')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }
}
