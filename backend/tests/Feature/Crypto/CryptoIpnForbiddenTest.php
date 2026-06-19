<?php

namespace Tests\Feature\Crypto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class CryptoIpnForbiddenTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_ipn_rejects_bad_path_secret(): void
    {
        $body = json_encode(['payment_status' => 'finished', 'order_id' => '50']);
        $sig = hash_hmac('sha512', (string) $body, 'test-ipn-hmac-secret');

        $this->withHeaders(['x-nowpayments-sig' => $sig])
            ->withBody((string) $body, 'application/json')
            ->post('/api/v1/crypto-ipn/wrong-secret')
            ->assertForbidden();
    }

    public function test_ipn_rejects_bad_signature(): void
    {
        $body = json_encode(['payment_status' => 'finished', 'order_id' => '50']);

        $this->withHeaders(['x-nowpayments-sig' => 'bad-signature'])
            ->withBody((string) $body, 'application/json')
            ->post('/api/v1/crypto-ipn/test-ipn-path-secret')
            ->assertForbidden();
    }
}
