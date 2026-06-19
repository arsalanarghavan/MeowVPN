<?php

namespace Tests\Feature\Crypto;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Mockery;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class CryptoIpnTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();

        $xui = Mockery::mock(XuiClient::class);
        $xui->shouldReceive('syncService')->andReturnNull();
        $this->app->instance(XuiClient::class, $xui);

        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        Http::fake([
            'https://panel.test/panel/api/server/getNewUUID' => Http::response(['success' => true, 'obj' => $uuid]),
            'https://panel.test/panel/api/inbounds/get/1' => Http::sequence()
                ->push(['success' => true, 'obj' => ['id' => 1, 'remark' => 'in', 'settings' => json_encode(['clients' => []])]])
                ->push(['success' => true, 'obj' => [
                    'id' => 1,
                    'remark' => 'in',
                    'settings' => json_encode(['clients' => [[
                        'id' => $uuid,
                        'email' => 'u101@svp.local',
                        'subId' => 'sub123',
                        'enable' => true,
                    ]]]),
                ]]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);
    }

    public function test_ipn_confirms_purchase_and_provisions_service(): void
    {
        $body = json_encode([
            'payment_status' => 'finished',
            'order_id' => '50',
            'payment_id' => 'np-pay-1',
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
}
