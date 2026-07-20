<?php

namespace App\Modules\Rial\Services;

use App\Modules\Rial\Jobs\RialFulfillJob;
use App\Modules\Rial\Support\GatewayHtmlResponse;
use App\Services\Commerce\TransactionFulfillService;
use App\Services\SettingsStore;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AqayepardakhtPaymentService
{
    public const API_BASE = 'https://panel.aqayepardakht.ir';

    public const MIN_AMOUNT_TOMAN = 1000;

    public const MAX_AMOUNT_TOMAN = 400000000;

    public function __construct(
        protected SettingsStore $settings,
        protected TransactionFulfillService $fulfill,
    ) {}

    public function isSandbox(): bool
    {
        return ! empty($this->settings->get('aqayepardakht_sandbox', false));
    }

    public function gatewayPin(): string
    {
        if ($this->isSandbox()) {
            return 'sandbox';
        }

        return trim((string) $this->settings->get('aqayepardakht_pin', ''));
    }

    public static function tomanForApi(float $toman): int
    {
        return (int) round($toman);
    }

    public static function amountValidationError(int $amount): string
    {
        if ($amount < self::MIN_AMOUNT_TOMAN) {
            return 'حداقل مبلغ پرداخت آقای پرداخت '.number_format(self::MIN_AMOUNT_TOMAN).' تومان است.';
        }
        if ($amount > self::MAX_AMOUNT_TOMAN) {
            return 'حداکثر مبلغ پرداخت آقای پرداخت '.number_format(self::MAX_AMOUNT_TOMAN).' تومان است.';
        }

        return '';
    }

    public function callbackUrl(int $txId): string
    {
        $path = (string) $this->settings->get('aqayepardakht_callback_path_secret', '');
        if ($path === '') {
            return '';
        }
        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');

        return $base.'/api/v1/aqayepardakht-callback/'.rawurlencode($path).'?svp_tx='.max(0, $txId);
    }

    public function callbackUrlSample(): string
    {
        return $this->callbackUrl(0);
    }

    public function startpayUrl(string $transid): string
    {
        $transid = rawurlencode(trim($transid));
        if ($this->isSandbox()) {
            return self::API_BASE.'/startpay/sandbox/'.$transid;
        }

        return self::API_BASE.'/startpay/'.$transid;
    }

    /** @return array{ok:bool, text?:string, reply_markup?:array<string, mixed>, pay_url?:string, message?:string} */
    public function createPayment(object $tx, string $platform = 'telegram'): array
    {
        unset($platform);
        $pin = $this->gatewayPin();
        if ($pin === '') {
            return ['ok' => false, 'message' => 'پین درگاه آقای پرداخت خالی است.'];
        }
        $txId = (int) $tx->id;
        $cb = $this->callbackUrl($txId);
        if ($cb === '') {
            return ['ok' => false, 'message' => 'رمز مسیر callback آقای پرداخت خالی است؛ یک‌بار ذخیرهٔ تنظیمات را بزنید.'];
        }
        $toman = (float) $tx->amount;
        $amount = self::tomanForApi($toman);
        $err = self::amountValidationError($amount);
        if ($err !== '') {
            return ['ok' => false, 'message' => $err];
        }
        $res = Http::timeout(12)
            ->acceptJson()
            ->asJson()
            ->post(self::API_BASE.'/api/v2/create', [
                'pin' => $pin,
                'amount' => $amount,
                'callback' => $cb,
                'callback_method' => 'GET',
                'invoice_id' => (string) $txId,
                'description' => 'MeowVPN tx '.$txId,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body() !== '' ? $res->body() : 'خطا در ایجاد تراکنش آقای پرداخت.'];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از آقای پرداخت.'];
        }
        if ((string) ($data['status'] ?? '') !== 'success') {
            $errMsg = isset($data['code']) ? (string) $data['code'] : $res->body();

            return ['ok' => false, 'message' => $errMsg !== '' ? $errMsg : 'خطا در ایجاد تراکنش آقای پرداخت.'];
        }
        $transid = isset($data['transid']) ? (string) $data['transid'] : '';
        if ($transid === '') {
            return ['ok' => false, 'message' => 'transid در پاسخ آقای پرداخت نبود.'];
        }
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['aqayepardakht_transid'] = $transid;
        $meta['aqayepardakht_amount_toman'] = $amount;
        $meta['payment_method'] = 'rial_aqayepardakht';
        DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);

        $payUrl = $this->startpayUrl($transid);
        $text = "💳 پرداخت آقای پرداخت\n➖➖➖➖➖➖➖➖\n🆔 سفارش: ".$txId."\n💵 مبلغ سفارش: ".number_format($toman)." تومان\n";
        $text .= "\n➡️ لینک پرداخت را باز کنید و پس از پرداخت به ربات برگردید.\n";

        return [
            'ok' => true,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🔗 پرداخت در آقای پرداخت', 'url' => $payUrl]],
                ],
            ],
            'pay_url' => $payUrl,
        ];
    }

    /** @return array{ok:bool, code?:string, message?:string} */
    public function verifyPayment(string $transid, int $amount): array
    {
        $transid = trim($transid);
        if ($transid === '' || $amount < 1) {
            return ['ok' => false, 'message' => 'bad_verify_input'];
        }
        $pin = $this->gatewayPin();
        if ($pin === '') {
            return ['ok' => false, 'message' => 'missing_pin'];
        }
        $res = Http::timeout(12)
            ->acceptJson()
            ->asJson()
            ->post(self::API_BASE.'/api/v2/verify', [
                'pin' => $pin,
                'amount' => $amount,
                'transid' => $transid,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body()];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'bad_verify_json'];
        }
        $vcode = isset($data['code']) ? (string) $data['code'] : '';
        if ((string) ($data['status'] ?? '') !== 'success' || ! in_array($vcode, ['1', '2'], true)) {
            $msg = $vcode !== '' ? $vcode : 'verify_failed';

            return ['ok' => false, 'message' => $msg, 'code' => $vcode];
        }

        return ['ok' => true, 'code' => $vcode];
    }

    public function handleCallback(Request $request, string $pathSecret): Response
    {
        $want = (string) $this->settings->get('aqayepardakht_callback_path_secret', '');
        if ($want === '' || ! hash_equals($want, $pathSecret)) {
            return GatewayHtmlResponse::make('دسترسی غیرمجاز', 'درخواست نامعتبر است.', false);
        }
        $status = trim((string) $request->query('status', ''));
        if ($status !== '1') {
            return GatewayHtmlResponse::make('پرداخت ناموفق', 'پرداخت لغو شد یا ناموفق بود. می‌توانید از ربات دوباره تلاش کنید.', false);
        }
        $transid = trim((string) $request->query('transid', ''));
        if ($transid === '') {
            return GatewayHtmlResponse::make('خطا', 'کد transid دریافت نشد.', false);
        }
        $txId = (int) $request->query('svp_tx', 0);
        if ($txId < 1) {
            return GatewayHtmlResponse::make('خطا', 'سفارش یافت نشد.', false);
        }
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        $allowedTypes = ['purchase', 'wallet_topup', 'topup', 'service_renew', 'service_add_volume', 'service_add_slots'];
        if (! $tx || ! in_array((string) $tx->type, $allowedTypes, true)) {
            return GatewayHtmlResponse::make('خطا', 'سفارش نامعتبر است.', false);
        }
        if ((string) $tx->status === 'approved') {
            return GatewayHtmlResponse::make('پرداخت موفق', 'این سفارش قبلاً تأیید شده است. به ربات برگردید.', true);
        }
        $txMeta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $txMeta = is_array($txMeta) ? $txMeta : [];
        $expected = isset($txMeta['aqayepardakht_transid']) ? (string) $txMeta['aqayepardakht_transid'] : '';
        if ($expected !== '' && ! hash_equals($expected, $transid)) {
            Log::error('aqayepardakht_callback transid mismatch', ['tx_id' => $txId]);

            return GatewayHtmlResponse::make('خطا', 'کد پرداخت با سفارش مطابقت ندارد.', false);
        }
        $amount = isset($txMeta['aqayepardakht_amount_toman'])
            ? (int) $txMeta['aqayepardakht_amount_toman']
            : self::tomanForApi((float) $tx->amount);
        $verify = $this->verifyPayment($transid, $amount);
        if (empty($verify['ok'])) {
            Log::error('aqayepardakht_callback verify failed', [
                'tx_id' => $txId,
                'message' => (string) ($verify['message'] ?? ''),
            ]);

            return GatewayHtmlResponse::make('خطا در تأیید', 'پرداخت تأیید نشد. در صورت کسر وجه با پشتیبانی تماس بگیرید.', false);
        }
        $tracking = trim((string) $request->query('tracking_number', ''));
        if ($tracking !== '') {
            $txMeta['aqayepardakht_tracking_number'] = $tracking;
            DB::table('svp_transactions')->where('id', $txId)->update([
                'meta_json' => json_encode($txMeta, JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ((string) $tx->status === 'pending' && ! $this->fulfill->claimPendingTransaction($txId)) {
            return GatewayHtmlResponse::make('پرداخت موفق', 'این سفارش قبلاً تأیید شده است. به ربات برگردید.', true);
        }
        RialFulfillJob::dispatch($txId, 'aqayepardakht');

        return GatewayHtmlResponse::make(
            'پرداخت موفق',
            'پرداخت شما با موفقیت ثبت شد. لطفاً به ربات برگردید تا سفارش تکمیل شود.',
            true
        );
    }
}
