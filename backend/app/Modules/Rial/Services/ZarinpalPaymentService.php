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

class ZarinpalPaymentService
{
    public function __construct(
        protected SettingsStore $settings,
        protected TransactionFulfillService $fulfill,
    ) {}

    public function isSandbox(): bool
    {
        return ! empty($this->settings->get('zarinpal_sandbox', false));
    }

    public function apiBaseUrl(): string
    {
        return $this->isSandbox() ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
    }

    public function payBaseUrl(): string
    {
        return $this->isSandbox() ? 'https://sandbox.zarinpal.com' : 'https://www.zarinpal.com';
    }

    public static function rialFromToman(float $toman): int
    {
        return max(1, (int) round($toman * 10));
    }

    public function callbackUrl(int $txId): string
    {
        $path = (string) $this->settings->get('zarinpal_callback_path_secret', '');
        if ($path === '') {
            return '';
        }
        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');

        return $base.'/api/v1/zarinpal-callback/'.rawurlencode($path).'?svp_tx='.max(0, $txId);
    }

    public function callbackUrlSample(): string
    {
        return $this->callbackUrl(0);
    }

    /** @return array{ok:bool, text?:string, reply_markup?:array<string, mixed>, pay_url?:string, message?:string} */
    public function requestPayment(object $tx, string $platform = 'telegram'): array
    {
        unset($platform);
        $merchant = trim((string) $this->settings->get('zarinpal_merchant_id', ''));
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'Merchant ID زرین‌پال خالی است.'];
        }
        $txId = (int) $tx->id;
        $cb = $this->callbackUrl($txId);
        if ($cb === '') {
            return ['ok' => false, 'message' => 'رمز مسیر callback زرین‌پال خالی است؛ یک‌بار ذخیرهٔ تنظیمات را بزنید.'];
        }
        $toman = (float) $tx->amount;
        $amount = self::rialFromToman($toman);
        $res = Http::timeout(12)
            ->acceptJson()
            ->asJson()
            ->post($this->apiBaseUrl().'/pg/v4/payment/request.json', [
                'merchant_id' => $merchant,
                'amount' => $amount,
                'callback_url' => $cb,
                'description' => 'MeowVPN tx '.$txId,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body() !== '' ? $res->body() : 'خطا در ایجاد درخواست زرین‌پال.'];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از زرین‌پال.'];
        }
        if (! empty($data['errors'])) {
            $err = is_array($data['errors']) ? json_encode($data['errors'], JSON_UNESCAPED_UNICODE) : (string) $data['errors'];

            return ['ok' => false, 'message' => $err !== '' ? $err : 'خطا در ایجاد درخواست زرین‌پال.'];
        }
        $inner = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        if ((int) ($inner['code'] ?? 0) !== 100) {
            $msg = isset($inner['message']) ? (string) $inner['message'] : $res->body();

            return ['ok' => false, 'message' => $msg !== '' ? $msg : 'خطا در ایجاد درخواست زرین‌پال.'];
        }
        $authority = isset($inner['authority']) ? (string) $inner['authority'] : '';
        if ($authority === '') {
            return ['ok' => false, 'message' => 'authority در پاسخ زرین‌پال نبود.'];
        }
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['zarinpal_authority'] = $authority;
        $meta['zarinpal_amount_rial'] = $amount;
        $meta['payment_method'] = 'rial_zarinpal';
        DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);

        $payUrl = $this->payBaseUrl().'/pg/StartPay/'.rawurlencode($authority);
        $text = "💳 پرداخت زرین‌پال\n➖➖➖➖➖➖➖➖\n🆔 سفارش: ".$txId."\n💵 مبلغ سفارش: ".number_format($toman)." تومان\n";
        $text .= "\n➡️ لینک پرداخت را باز کنید و پس از پرداخت به ربات برگردید.\n";
        $markup = [
            'inline_keyboard' => [
                [['text' => '🔗 پرداخت در زرین‌پال', 'url' => $payUrl]],
            ],
        ];

        return ['ok' => true, 'text' => $text, 'reply_markup' => $markup, 'pay_url' => $payUrl];
    }

    /** @return array{ok:bool, code?:int, ref_id?:int, message?:string} */
    public function verifyPayment(string $authority, int $amount): array
    {
        $authority = trim($authority);
        if ($authority === '' || $amount < 1) {
            return ['ok' => false, 'message' => 'bad_verify_input'];
        }
        $merchant = trim((string) $this->settings->get('zarinpal_merchant_id', ''));
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'missing_merchant_id'];
        }
        $res = Http::timeout(12)
            ->acceptJson()
            ->asJson()
            ->post($this->apiBaseUrl().'/pg/v4/payment/verify.json', [
                'merchant_id' => $merchant,
                'authority' => $authority,
                'amount' => $amount,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body()];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'bad_verify_json'];
        }
        if (! empty($data['errors'])) {
            $msg = is_array($data['errors']) ? json_encode($data['errors'], JSON_UNESCAPED_UNICODE) : (string) $data['errors'];

            return ['ok' => false, 'message' => $msg];
        }
        $inner = isset($data['data']) && is_array($data['data']) ? $data['data'] : [];
        $code = (int) ($inner['code'] ?? 0);
        if (! in_array($code, [100, 101], true)) {
            $msg = isset($inner['message']) ? (string) $inner['message'] : 'verify_failed';

            return ['ok' => false, 'message' => $msg, 'code' => $code];
        }

        return [
            'ok' => true,
            'code' => $code,
            'ref_id' => isset($inner['ref_id']) ? (int) $inner['ref_id'] : 0,
        ];
    }

    public function handleCallback(Request $request, string $pathSecret): Response
    {
        $want = (string) $this->settings->get('zarinpal_callback_path_secret', '');
        if ($want === '' || ! hash_equals($want, $pathSecret)) {
            return GatewayHtmlResponse::make('دسترسی غیرمجاز', 'درخواست نامعتبر است.', false);
        }
        $status = strtoupper(trim((string) $request->query('Status', '')));
        if ($status !== 'OK') {
            return GatewayHtmlResponse::make('پرداخت ناموفق', 'پرداخت لغو شد یا ناموفق بود. می‌توانید از ربات دوباره تلاش کنید.', false);
        }
        $authority = trim((string) $request->query('Authority', ''));
        if ($authority === '') {
            return GatewayHtmlResponse::make('خطا', 'کد authority دریافت نشد.', false);
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
        $expected = isset($txMeta['zarinpal_authority']) ? (string) $txMeta['zarinpal_authority'] : '';
        if ($expected !== '' && ! hash_equals($expected, $authority)) {
            Log::error('zarinpal_callback authority mismatch', ['tx_id' => $txId]);

            return GatewayHtmlResponse::make('خطا', 'کد پرداخت با سفارش مطابقت ندارد.', false);
        }
        $amount = isset($txMeta['zarinpal_amount_rial'])
            ? (int) $txMeta['zarinpal_amount_rial']
            : self::rialFromToman((float) $tx->amount);
        $verify = $this->verifyPayment($authority, $amount);
        if (empty($verify['ok'])) {
            Log::error('zarinpal_callback verify failed', [
                'tx_id' => $txId,
                'message' => (string) ($verify['message'] ?? ''),
            ]);

            return GatewayHtmlResponse::make('خطا در تأیید', 'پرداخت تأیید نشد. در صورت کسر وجه با پشتیبانی تماس بگیرید.', false);
        }
        if (! empty($verify['ref_id'])) {
            $txMeta['zarinpal_ref_id'] = (int) $verify['ref_id'];
            DB::table('svp_transactions')->where('id', $txId)->update([
                'meta_json' => json_encode($txMeta, JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ((string) $tx->status === 'pending' && ! $this->fulfill->claimPendingTransaction($txId)) {
            return GatewayHtmlResponse::make('پرداخت موفق', 'این سفارش قبلاً تأیید شده است. به ربات برگردید.', true);
        }
        RialFulfillJob::dispatch($txId, 'zarinpal');

        return GatewayHtmlResponse::make(
            'پرداخت موفق',
            'پرداخت شما با موفقیت ثبت شد. لطفاً به ربات برگردید تا سفارش تکمیل شود.',
            true
        );
    }
}
