<?php

namespace Tests\Feature\Mutate;

use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §15.1 — settings_tab keys behavioral coverage */
class SettingsTabMutateTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    /** @return array<string, array{0: string}> */
    public static function allowedTabsProvider(): array
    {
        return [
            'general' => ['general'],
            'referral' => ['referral'],
            'whitelabel' => ['whitelabel'],
            'notifications' => ['notifications'],
            'purge_expired' => ['purge_expired'],
            'resellers_defaults' => ['resellers_defaults'],
            'proxy' => ['proxy'],
            'relay' => ['relay'],
            'finance' => ['finance'],
            'plans_catalog' => ['plans_catalog'],
            'cards' => ['cards'],
            'force_join' => ['force_join'],
            'receipts' => ['receipts'],
            'backup' => ['backup'],
            'service_naming' => ['service_naming'],
            'logs' => ['logs'],
            'bots' => ['bots'],
            'landing' => ['landing'],
            'subscription_portal' => ['subscription_portal'],
            'cron' => ['cron'],
        ];
    }

    public function test_settings_tab_deprecated_panel_key_rejected(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'panel',
            'foo' => 'bar',
        ])->assertOk()->assertJsonPath('message', 'invalid_tab');
    }

    /** @dataProvider allowedTabsProvider */
    public function test_settings_tab_allowed_keys_save(string $tab): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => $tab,
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
