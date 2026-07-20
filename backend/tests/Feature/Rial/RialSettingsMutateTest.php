<?php

namespace Tests\Feature\Rial;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class RialSettingsMutateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_rial_settings_saves_when_module_enabled(): void
    {
        $this->setModuleEnabled('rial', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'rial_settings',
            'zarinpal_merchant_id' => 'merchant-xyz',
            'zarinpal_sandbox' => 1,
            'zibal_merchant' => 'zibal-mid',
            'aqayepardakht_pin' => 'pin-123',
        ])->assertOk()->assertJsonPath('ok', true);

        $settings = app(\App\Services\SettingsStore::class);
        $this->assertSame('merchant-xyz', (string) $settings->get('zarinpal_merchant_id'));
        $this->assertNotSame('', (string) $settings->get('zarinpal_callback_path_secret', ''));
    }

    public function test_rial_settings_rejected_when_module_disabled(): void
    {
        $this->setModuleEnabled('rial', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'rial_settings',
            'zarinpal_sandbox' => 0,
        ])->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'module_disabled');
    }
}
