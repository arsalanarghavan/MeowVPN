<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group B — Site Settings */
class GroupBSiteSettingsAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_settings_tab_general(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'general',
            'site_name' => 'Test Site',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_settings_tab_referral(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'referral',
            'referral_enabled' => true,
        ])->assertOk();
    }

    public function test_logs_clear_admin_only(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', ['op' => 'logs_clear'])
            ->assertOk();
    }

    public function test_settings_tab_whitelabel(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'whitelabel',
            'brand_name' => 'Test Brand',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_settings_tab_notifications(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'notifications',
            'alert_low_traffic_pct' => 15,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_settings_tab_purge_expired(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'purge_expired',
            'purge_expired_enabled' => true,
        ])->assertOk();
    }

    public function test_purge_expired_list_endpoint(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/purge-expired')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_admin_logs_pagination(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/logs?offset=0&limit=10')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_settings_tab_resellers_defaults(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'resellers_defaults',
            'permissions' => ['users.manage' => true, 'plans.manage' => false],
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_purge_expired_purge_ready_mutate(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_purge_ready',
        ])->assertOk();
    }
}
