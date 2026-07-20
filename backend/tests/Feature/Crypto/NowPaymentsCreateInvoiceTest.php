<?php

namespace Tests\Feature\Crypto;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class NowPaymentsCreateInvoiceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('crypto', true);

        $settings = app(\App\Services\SettingsStore::class);
        $settings->set('crypto_nowpayments_api_key', 'np-test-key');
        $settings->set('crypto_ipn_path_secret', 'test-ipn-path-secret');
        $settings->set('crypto_toman_per_usd', 50000);
        $settings->set('crypto_nowpayments_pay_currency', 'usdttrc20');
        $settings->set('public_site_url', 'https://meow.test');
    }

    public function test_create_invoice_stores_payment_id_and_returns_pay_url(): void
    {
        Http::fake([
            'https://api.nowpayments.io/v1/payment' => Http::response([
                'payment_id' => 'np-pay-99',
                'invoice_url' => 'https://nowpayments.io/payment/?iid=99',
                'pay_address' => 'TXaddr',
                'pay_amount' => '1.5',
                'pay_currency' => 'usdttrc20',
            ], 201),
        ]);

        $tx = DB::table('svp_transactions')->where('id', 50)->first();
        $this->assertNotNull($tx);

        $service = app(\App\Modules\Crypto\Services\NowPaymentsPaymentService::class);
        $res = $service->createInvoice($tx, 'telegram');

        $this->assertTrue($res['ok'] ?? false);
        $this->assertStringContainsString('nowpayments.io', (string) ($res['pay_url'] ?? ''));

        $meta = json_decode((string) DB::table('svp_transactions')->where('id', 50)->value('meta_json'), true);
        $this->assertSame('np-pay-99', $meta['nowpayments_payment_id'] ?? null);
    }

    public function test_start_gateway_invoice_crypto_auto(): void
    {
        Http::fake([
            'https://api.nowpayments.io/v1/payment' => Http::response([
                'payment_id' => 'np-pay-auto',
                'pay_url' => 'https://nowpayments.io/payment/?iid=auto',
            ], 201),
        ]);

        $user = \App\Models\SvpUser::query()->find(101);
        $this->assertNotNull($user);
        $card = (object) ['id' => 1, 'method_key' => 'crypto_auto'];
        $checkout = app(\App\Modules\Core\Bot\Services\BotCommerceCheckoutService::class);
        $res = $checkout->startGatewayInvoice($user, 50, $card, 'telegram');
        $this->assertTrue($res['ok'] ?? false);
    }
}
