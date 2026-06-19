<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group D — Bot Settings */
class GroupDBotSettingsAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bot_diagnostics(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', ['op' => 'bot_diagnostics'])
            ->assertOk();
    }

    public function test_force_join_publish(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
            'enabled' => true,
        ])->assertOk();
    }

    public function test_texts_save_and_reset(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_save',
            'key' => 'welcome',
            'value' => 'Hello acceptance',
        ])->assertOk();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'text_reset_one',
            'key' => 'welcome',
        ])->assertOk();
    }

    public function test_bot_ui_layout_save_admin_only(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_save',
            'version' => 1,
            'surfaces' => [],
        ])->assertOk();
    }

    public function test_bot_ui_layout_save_forbidden_for_reseller(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_ui_layout_save',
            'version' => 1,
            'surfaces' => [],
        ])->assertForbidden();
    }

    public function test_reseller_bots_tab_in_state(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/state?tab=reseller_bots')
            ->assertOk()
            ->assertJsonStructure(['botsList']);
    }
}
