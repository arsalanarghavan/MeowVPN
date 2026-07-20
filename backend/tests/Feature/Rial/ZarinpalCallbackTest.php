<?php

namespace Tests\Feature\Rial;

use App\Modules\Rial\Jobs\RialFulfillJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class ZarinpalCallbackTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('rial', true);

        Http::fake([
            'https://payment.zarinpal.com/pg/v4/payment/verify.json' => Http::response([
                'data' => ['code' => 100, 'ref_id' => 98765, 'message' => 'Paid'],
                'errors' => [],
            ]),
        ]);

        app(\App\Services\SettingsStore::class)->set('zarinpal_callback_path_secret', 'test-zarinpal-path');
        app(\App\Services\SettingsStore::class)->set('zarinpal_merchant_id', 'test-merchant');
        DB::table('svp_transactions')->where('id', 50)->update([
            'meta_json' => json_encode([
                'plan_id' => 1,
                'zarinpal_authority' => 'A00000000000000000000000000000000000',
                'zarinpal_amount_rial' => 100000,
            ], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public function test_zarinpal_callback_verifies_and_queues_fulfill(): void
    {
        Bus::fake([RialFulfillJob::class]);

        $this->get('/api/v1/zarinpal-callback/test-zarinpal-path?Status=OK&Authority=A00000000000000000000000000000000000&svp_tx=50')
            ->assertOk()
            ->assertSee('پرداخت موفق', false);

        $this->assertSame('processing', DB::table('svp_transactions')->where('id', 50)->value('status'));
        Bus::assertDispatched(RialFulfillJob::class, function (RialFulfillJob $job) {
            return $job->transactionId === 50 && $job->source === 'zarinpal';
        });
    }

    public function test_zarinpal_callback_forbidden_bad_secret(): void
    {
        $this->get('/api/v1/zarinpal-callback/wrong-secret?Status=OK&Authority=A&svp_tx=50')
            ->assertOk()
            ->assertSee('دسترسی غیرمجاز', false);
    }
}
