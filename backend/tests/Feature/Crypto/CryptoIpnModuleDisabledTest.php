<?php

namespace Tests\Feature\Crypto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class CryptoIpnModuleDisabledTest extends TestCase
{
    use RefreshDatabase;
    use TogglesModules;

    public function test_ipn_returns_module_disabled_when_crypto_off(): void
    {
        $this->setModuleEnabled('crypto', false);

        $this->post('/api/v1/crypto-ipn/test-secret', [], [
            'Content-Type' => 'application/json',
        ])->assertStatus(503)->assertJsonPath('message', 'module_disabled');
    }
}
