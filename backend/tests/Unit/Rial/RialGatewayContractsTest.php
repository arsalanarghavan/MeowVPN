<?php

namespace Tests\Unit\Rial;

use App\Modules\Crypto\Services\TetraPaymentService;
use App\Modules\Rial\Services\AqayepardakhtPaymentService;
use App\Modules\Rial\Services\ZarinpalPaymentService;
use App\Modules\Rial\Services\ZibalPaymentService;
use PHPUnit\Framework\TestCase;

class RialGatewayContractsTest extends TestCase
{
    public function test_zarinpal_rial_from_toman(): void
    {
        $this->assertSame(500000, ZarinpalPaymentService::rialFromToman(50000));
        $this->assertSame(100004, ZarinpalPaymentService::rialFromToman(10000.4));
        $this->assertSame(100000, ZarinpalPaymentService::rialFromToman(10000));
    }

    public function test_zibal_rial_from_toman(): void
    {
        $this->assertSame(500000, ZibalPaymentService::rialFromToman(50000));
    }

    public function test_aqayepardakht_amount_validation(): void
    {
        $this->assertSame(1000, AqayepardakhtPaymentService::tomanForApi(1000.4));
        $this->assertNotSame('', AqayepardakhtPaymentService::amountValidationError(100));
        $this->assertSame('', AqayepardakhtPaymentService::amountValidationError(5000));
    }

    public function test_tetra_hash_id_roundtrip(): void
    {
        $this->assertSame('svp-42', TetraPaymentService::hashIdForTx(42));
        $this->assertSame(42, TetraPaymentService::txIdFromHashId('svp-42'));
        $this->assertSame(7, TetraPaymentService::txIdFromHashId('7'));
        $this->assertSame(0, TetraPaymentService::txIdFromHashId('bad'));
    }
}
