<?php

namespace Tests\Feature\Crypto;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Crypto module CI profile — IPN HMAC + fulfill acceptance (v18). */
class CryptoModuleAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('crypto', true);

        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('syncService')->andReturnNull();
        $this->app->instance(XuiClient::class, $xui);

        Http::fake([
            'https://panel.test/panel/api/server/getNewUUID' => Http::response(['success' => true, 'obj' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee']),
            'https://panel.test/panel/api/inbounds/get/1' => Http::sequence()
                ->push(['success' => true, 'obj' => ['id' => 1, 'remark' => 'in', 'settings' => json_encode(['clients' => []])]])
                ->push(['success' => true, 'obj' => [
                    'id' => 1,
                    'remark' => 'in',
                    'settings' => json_encode(['clients' => [[
                        'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                        'email' => 'u101@svp.local',
                        'subId' => 'sub123',
                        'enable' => true,
                    ]]]),
                ]]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);
    }

    public function test_crypto_module_enabled_ipn_fulfills_transaction(): void
    {
        $body = json_encode([
            'payment_status' => 'finished',
            'order_id' => '50',
            'payment_id' => 'np-pay-v18',
        ], JSON_UNESCAPED_UNICODE);
        $sig = hash_hmac('sha512', (string) $body, 'test-ipn-hmac-secret');

        $this->withHeaders(['x-nowpayments-sig' => $sig])
            ->withBody((string) $body, 'application/json')
            ->post('/api/v1/crypto-ipn/test-ipn-path-secret')
            ->assertOk()
            ->assertJsonPath('ok', true);

        $this->assertSame('approved', DB::table('svp_transactions')->where('id', 50)->value('status'));
        $this->assertNotNull(DB::table('svp_transactions')->where('id', 50)->value('service_id'));
    }

    public function test_crypto_settings_mutate_when_module_enabled(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'crypto_settings',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
