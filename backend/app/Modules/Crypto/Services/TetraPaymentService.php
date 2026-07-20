<?php

namespace App\Modules\Crypto\Services;

use App\Modules\Crypto\Jobs\CryptoFulfillJob;
use App\Services\Commerce\TransactionFulfillService;
use App\Services\SettingsStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TetraPaymentService
{
    public const API_CREATE = 'https://tetra98.com/api/create_order';

    public const API_VERIFY = 'https://tetra98.com/api/verify';

    public function __construct(
        protected SettingsStore $settings,
        protected TransactionFulfillService $fulfill,
    ) {}

    public function callbackUrl(): string
    {
        $path = (string) $this->settings->get('crypto_tetra_callback_path_secret', '');
        if ($path === '') {
            return '';
        }
        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');

        return $base.'/api/v1/tetra-callback/'.rawurlencode($path);
    }

    public static function hashIdForTx(int $txId): string
    {
        return 'svp-'.max(1, $txId);
    }

    public static function txIdFromHashId(string $hashid): int
    {
        $h = trim($hashid);
        if (preg_match('/^svp-(\d+)$/i', $h, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/^\d+$/', $h)) {
            return (int) $h;
        }

        return 0;
    }

    /** @return array{ok:bool, text?:string, reply_markup?:array<string, mixed>, pay_url?:string, message?:string} */
    public function createOrder(object $tx, string $platform = 'telegram'): array
    {
        $api = trim((string) $this->settings->get('crypto_tetra_api_key', ''));
        if ($api === '') {
            return ['ok' => false, 'message' => 'API key تتراپی خالی است.'];
        }
        $cb = $this->callbackUrl();
        if ($cb === '') {
            return ['ok' => false, 'message' => 'رمز مسیر callback تتراپی خالی است؛ یک‌بار ذخیرهٔ تنظیمات را بزنید.'];
        }
        $toman = (float) $tx->amount;
        $amount = max(1, (int) round($toman * 10));
        $txId = (int) $tx->id;
        $hashId = self::hashIdForTx($txId);
        $res = Http::timeout(12)
            ->asJson()
            ->post(self::API_CREATE, [
                'ApiKey' => $api,
                'Hash_id' => $hashId,
                'Amount' => $amount,
                'Description' => 'MeowVPN tx '.$txId,
                'CallbackURL' => $cb,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body() !== '' ? $res->body() : 'خطا در ایجاد سفارش TetraPay.'];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از TetraPay.'];
        }
        if ((string) ($data['status'] ?? '') !== '100') {
            $err = isset($data['message']) ? (string) $data['message'] : $res->body();

            return ['ok' => false, 'message' => $err !== '' ? $err : 'خطا در ایجاد سفارش TetraPay.'];
        }
        $authority = isset($data['Authority']) ? (string) $data['Authority'] : '';
        if ($authority === '') {
            return ['ok' => false, 'message' => 'Authority در پاسخ TetraPay نبود.'];
        }
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['tetra_authority'] = $authority;
        $meta['tetra_hash_id'] = $hashId;
        $meta['tetra_tracking_id'] = isset($data['tracking_id']) ? (string) $data['tracking_id'] : '';
        $meta['payment_method'] = 'crypto_tetra';
        DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);

        $payUrl = '';
        if (strtolower(trim($platform)) === 'telegram') {
            $payUrl = isset($data['payment_url_bot']) ? (string) $data['payment_url_bot'] : '';
        } else {
            $payUrl = isset($data['payment_url_web']) ? (string) $data['payment_url_web'] : '';
        }
        if ($payUrl === '') {
            foreach (['payment_url_bot', 'payment_url_web'] as $k) {
                if (! empty($data[$k]) && is_string($data[$k])) {
                    $payUrl = (string) $data[$k];
                    break;
                }
            }
        }
        if ($payUrl === '') {
            return ['ok' => false, 'message' => 'لینک پرداخت در پاسخ TetraPay نبود.'];
        }
        $text = "💳 پرداخت TetraPay\n➖➖➖➖➖➖➖➖\n🆔 سفارش: ".$txId."\n💵 مبلغ سفارش: ".number_format($toman)." تومان\n";
        $text .= "\n➡️ لینک پرداخت را باز کنید و طبق راهنما پرداخت را تمام کنید.\n";

        return [
            'ok' => true,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🔗 پرداخت در TetraPay', 'url' => $payUrl]],
                ],
            ],
            'pay_url' => $payUrl,
        ];
    }

    /** @return array{ok:bool, hash_id?:string, message?:string} */
    public function verifyAuthority(string $authority): array
    {
        $authority = trim($authority);
        if ($authority === '') {
            return ['ok' => false, 'message' => 'empty_authority'];
        }
        $api = trim((string) $this->settings->get('crypto_tetra_api_key', ''));
        if ($api === '') {
            return ['ok' => false, 'message' => 'missing_api_key'];
        }
        $res = Http::timeout(12)
            ->asJson()
            ->post(self::API_VERIFY, [
                'ApiKey' => $api,
                'authority' => $authority,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body()];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'bad_verify_json'];
        }
        $st = (string) ($data['status'] ?? '');
        if ($st !== '100') {
            $msg = isset($data['message']) ? (string) $data['message'] : 'verify_failed';

            return ['ok' => false, 'message' => $msg];
        }
        $hash = isset($data['hash_id']) ? (string) $data['hash_id'] : '';
        if ($hash === '' && isset($data['Hash_id'])) {
            $hash = (string) $data['Hash_id'];
        }

        return ['ok' => true, 'hash_id' => $hash];
    }

    public function handleCallback(Request $request, string $pathSecret): JsonResponse
    {
        $want = (string) $this->settings->get('crypto_tetra_callback_path_secret', '');
        if ($want === '' || ! hash_equals($want, $pathSecret)) {
            return response()->json(['error' => 'forbidden'], 403);
        }
        $raw = $request->getContent();
        if ($raw === '') {
            return response()->json(['ok' => true, 'note' => 'empty']);
        }
        $data = json_decode($raw, true);
        if (! is_array($data)) {
            $data = [];
            parse_str($raw, $data);
        }
        if (! is_array($data) || $data === []) {
            return response()->json(['error' => 'bad_json'], 400);
        }
        $status = $data['status'] ?? '';
        if ((string) $status !== '100' && $status !== 100) {
            return response()->json(['ok' => true, 'ignored' => $status]);
        }
        $hashid = isset($data['hashid']) ? (string) $data['hashid'] : '';
        if ($hashid === '' && isset($data['Hash_id'])) {
            $hashid = (string) $data['Hash_id'];
        }
        $authority = isset($data['authority']) ? (string) $data['authority'] : '';
        if ($authority === '' && isset($data['Authority'])) {
            $authority = (string) $data['Authority'];
        }
        if ($authority === '') {
            return response()->json(['error' => 'no_authority'], 400);
        }
        $verify = $this->verifyAuthority($authority);
        if (empty($verify['ok'])) {
            Log::error('tetra_callback verify failed', [
                'authority' => $authority,
                'message' => (string) ($verify['message'] ?? ''),
            ]);

            return response()->json(['error' => 'verify_failed'], 400);
        }
        if ($hashid === '' && ! empty($verify['hash_id'])) {
            $hashid = (string) $verify['hash_id'];
        }
        $txId = self::txIdFromHashId($hashid);
        if ($txId < 1) {
            return response()->json(['error' => 'bad_hashid'], 400);
        }
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        $allowedTypes = ['purchase', 'wallet_topup', 'topup', 'service_renew', 'service_add_volume', 'service_add_slots'];
        if (! $tx || ! in_array((string) $tx->type, $allowedTypes, true)) {
            return response()->json(['error' => 'bad_tx'], 400);
        }
        if ((string) $tx->status === 'approved') {
            return response()->json(['ok' => true, 'note' => 'already_approved']);
        }
        $txMeta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $txMeta = is_array($txMeta) ? $txMeta : [];
        $expected = isset($txMeta['tetra_authority']) ? (string) $txMeta['tetra_authority'] : '';
        if ($expected !== '' && ! hash_equals($expected, $authority)) {
            Log::error('tetra_callback authority mismatch', ['tx_id' => $txId]);

            return response()->json(['error' => 'authority_mismatch'], 409);
        }
        if ((string) $tx->status === 'pending' && ! $this->fulfill->claimPendingTransaction($txId)) {
            return response()->json(['ok' => true, 'note' => 'already_claimed']);
        }
        CryptoFulfillJob::dispatch($txId, 'tetra');

        return response()->json(['ok' => true, 'queued' => true]);
    }
}
