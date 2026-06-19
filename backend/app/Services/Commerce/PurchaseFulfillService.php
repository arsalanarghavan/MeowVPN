<?php

namespace App\Services\Commerce;

class PurchaseFulfillService
{
    public function __construct(protected TransactionFulfillService $fulfill) {}

    /** @return array{ok:bool, reason?:string, service_id?:int} */
    public function fulfillByTransaction(int $txId, string $source = 'nowpayments'): array
    {
        return $this->fulfill->fulfillByTransaction($txId, $source);
    }
}
