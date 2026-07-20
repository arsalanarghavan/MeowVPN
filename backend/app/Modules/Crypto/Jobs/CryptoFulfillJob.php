<?php

namespace App\Modules\Crypto\Jobs;

use App\Services\Commerce\PurchaseFulfillService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class CryptoFulfillJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $transactionId,
        public string $source = 'nowpayments',
    ) {}

    public function handle(PurchaseFulfillService $fulfill): void
    {
        $key = 'svp_crypto_fulfill_try_'.$this->source.'_'.$this->transactionId;
        $attempt = (int) Cache::get($key, 0);

        $res = $fulfill->fulfillByTransaction($this->transactionId, $this->source);
        if (! empty($res['ok'])) {
            Cache::forget($key);

            return;
        }

        $reason = (string) ($res['reason'] ?? '');
        if (in_array($reason, ['bad_tx', 'no_plan_id'], true)) {
            return;
        }

        if ($attempt >= 2) {
            Cache::forget($key);

            return;
        }

        Cache::put($key, $attempt + 1, 3600);
        self::dispatch($this->transactionId, $this->source)->delay(now()->addSeconds(30 * ($attempt + 1)));
    }
}
