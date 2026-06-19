<?php

namespace Tests\Feature\Acceptance;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 B.1.1 — CSS vars round-trip */
class WhitelabelCssAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_whitelabel_save_builds_css_variables(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'whitelabel',
            'branding_theme_primary' => '#112233',
            'branding_theme_accent' => '#aabbcc',
            'css_variables_custom' => ['--svp-test-var' => '42px'],
        ])->assertOk()->assertJsonPath('ok', true);

        $store = app(SettingsStore::class);
        $this->assertSame('#112233', (string) $store->get('branding_theme_primary'));

        $wl = $store->get('whitelabel', []);
        $this->assertIsArray($wl);
        $vars = $wl['cssVariables'] ?? [];
        $this->assertSame('#112233', $vars['--svp-brand-primary'] ?? '');
        $this->assertSame('42px', $vars['--svp-test-var'] ?? '');

        $boot = $this->getJson('/api/v1/bootstrap')->assertOk()->json('branding.cssVariables');
        $this->assertSame('#112233', $boot['--svp-brand-primary'] ?? '');
    }
}
