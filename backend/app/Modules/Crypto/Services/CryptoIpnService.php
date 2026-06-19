<?php

namespace App\Modules\Crypto\Services;

use App\Modules\Crypto\Jobs\CryptoFulfillJob;
use App\Services\Commerce\TransactionFulfillService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class CryptoIpnService
{
    public function __construct(
        protected SettingsStore $settings,
        protected TransactionFulfillService $fulfill,
    ) {}

    /** @return array{ok:bool, message?:string, error?:string, status?:int} */
    public function handle(string $pathSecret, string $rawBody, ?string $signature): array
    {
        $want = (string) $this->settings->get('crypto_ipn_path_secret', '');
        if ($want === '' || ! hash_equals($want, $pathSecret)) {
            return ['ok' => false, 'error' => 'forbidden', 'status' => 403];
        }

        if ($rawBody === '') {
            return ['ok' => true, 'message' => 'empty'];
        }

        $ipnSecret = trim((string) $this->settings->get('crypto_nowpayments_ipn_secret', ''));
        if ($ipnSecret === '') {
            return ['ok' => false, 'error' => 'ipn_secret_required', 'status' => 403];
        }

        $sig = trim((string) $signature);
        if ($sig === '') {
            return ['ok' => false, 'error' => 'no_sig', 'status' => 403];
        }

        $calc = hash_hmac('sha512', $rawBody, $ipnSecret);
        if (! hash_equals($calc, $sig)) {
            return ['ok' => false, 'error' => 'bad_sig', 'status' => 403];
        }

        $data = json_decode($rawBody, true);
        if (! is_array($data)) {
            return ['ok' => false, 'error' => 'bad_json', 'status' => 400];
        }

        $status = (string) ($data['payment_status'] ?? '');
        if (! in_array($status, ['finished', 'confirmed'], true)) {
            return ['ok' => true, 'message' => 'ignored', 'status' => $status];
        }

        $oid = (string) ($data['order_id'] ?? '');
        if (! preg_match('/^\d+$/', $oid)) {
            return ['ok' => false, 'error' => 'bad_order', 'status' => 400];
        }

        $txId = (int) $oid;
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        if (! $tx || (string) $tx->status !== 'pending') {
            return ['ok' => false, 'error' => 'bad_tx', 'status' => 400];
        }

        $allowed = ['purchase', 'service_renew', 'service_add_volume', 'service_add_slots', 'wallet_topup'];
        if (! in_array((string) $tx->type, $allowed, true)) {
            return ['ok' => false, 'error' => 'bad_tx', 'status' => 400];
        }

        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        if (! is_array($meta)) {
            $meta = [];
        }

        $expectedPid = (string) ($meta['nowpayments_payment_id'] ?? '');
        $incomingPid = (string) ($data['payment_id'] ?? '');
        if ($expectedPid !== '' && $incomingPid !== '' && $expectedPid !== $incomingPid) {
            return ['ok' => false, 'error' => 'payment_id_mismatch', 'status' => 409];
        }

        if (! $this->fulfill->claimPendingTransaction($txId)) {
            return ['ok' => true, 'message' => 'already_claimed'];
        }

        CryptoFulfillJob::dispatch($txId);

        return ['ok' => true, 'message' => 'queued'];
    }
}
