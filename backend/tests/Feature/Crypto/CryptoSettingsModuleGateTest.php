<?php

namespace Tests\Feature\Crypto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class CryptoSettingsModuleGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('crypto', false);
    }

    public function test_crypto_settings_mutate_rejected_when_module_disabled(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'crypto_settings',
            'crypto_enabled' => true,
        ])->assertForbidden()
            ->assertJsonPath('ok', false)
            ->assertJsonPath('message', 'module_disabled');
    }
}
