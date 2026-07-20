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

class ZibalPaymentService
{
    public const API_BASE = 'https://gateway.zibal.ir';

    public function __construct(
        protected SettingsStore $settings,
        protected TransactionFulfillService $fulfill,
    ) {}

    public function isSandbox(): bool
    {
        return ! empty($this->settings->get('zibal_sandbox', false));
    }

    public function gatewayMerchant(): string
    {
        if ($this->isSandbox()) {
            return 'zibal';
        }

        return trim((string) $this->settings->get('zibal_merchant', ''));
    }

    public static function rialFromToman(float $toman): int
    {
        return max(1, (int) round($toman * 10));
    }

    public function callbackUrl(int $txId): string
    {
        $path = (string) $this->settings->get('zibal_callback_path_secret', '');
        if ($path === '') {
            return '';
        }
        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');

        return $base.'/api/v1/zibal-callback/'.rawurlencode($path).'?svp_tx='.max(0, $txId);
    }

    public function callbackUrlSample(): string
    {
        return $this->callbackUrl(0);
    }

    public function startpayUrl(string|int $trackId): string
    {
        return self::API_BASE.'/start/'.rawurlencode(trim((string) $trackId));
    }

    /** @return array{ok:bool, text?:string, reply_markup?:array<string, mixed>, pay_url?:string, message?:string} */
    public function requestPayment(object $tx, string $platform = 'telegram'): array
    {
        unset($platform);
        $merchant = $this->gatewayMerchant();
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'شناسه درگاه زیبال خالی است.'];
        }
        $txId = (int) $tx->id;
        $cb = $this->callbackUrl($txId);
        if ($cb === '') {
            return ['ok' => false, 'message' => 'رمز مسیر callback زیبال خالی است؛ یک‌بار ذخیرهٔ تنظیمات را بزنید.'];
        }
        $toman = (float) $tx->amount;
        $amount = self::rialFromToman($toman);
        $res = Http::timeout(12)
            ->acceptJson()
            ->asJson()
            ->post(self::API_BASE.'/v1/request', [
                'merchant' => $merchant,
                'amount' => $amount,
                'callbackUrl' => $cb,
                'description' => 'MeowVPN tx '.$txId,
                'orderId' => (string) $txId,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body() !== '' ? $res->body() : 'خطا در ایجاد درخواست زیبال.'];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از زیبال.'];
        }
        $result = (int) ($data['result'] ?? 0);
        if ($result !== 100) {
            $msg = isset($data['message']) ? (string) $data['message'] : $res->body();

            return ['ok' => false, 'message' => $msg !== '' ? $msg : 'خطا در ایجاد درخواست زیبال.'];
        }
        $trackId = isset($data['trackId']) ? (string) $data['trackId'] : '';
        if ($trackId === '') {
            return ['ok' => false, 'message' => 'trackId در پاسخ زیبال نبود.'];
        }
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        $meta['zibal_track_id'] = $trackId;
        $meta['zibal_amount_rial'] = $amount;
        $meta['payment_method'] = 'rial_zibal';
        DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE)]);

        $payUrl = $this->startpayUrl($trackId);
        $text = "💳 پرداخت زیبال\n➖➖➖➖➖➖➖➖\n🆔 سفارش: ".$txId."\n💵 مبلغ سفارش: ".number_format($toman)." تومان\n";
        $text .= "\n➡️ لینک پرداخت را باز کنید و پس از پرداخت به ربات برگردید.\n";

        return [
            'ok' => true,
            'text' => $text,
            'reply_markup' => [
                'inline_keyboard' => [
                    [['text' => '🔗 پرداخت در زیبال', 'url' => $payUrl]],
                ],
            ],
            'pay_url' => $payUrl,
        ];
    }

    /** @return array{ok:bool, result?:int, message?:string} */
    public function verifyPayment(string|int $trackId): array
    {
        $trackId = trim((string) $trackId);
        if ($trackId === '') {
            return ['ok' => false, 'message' => 'bad_verify_input'];
        }
        $merchant = $this->gatewayMerchant();
        if ($merchant === '') {
            return ['ok' => false, 'message' => 'missing_merchant'];
        }
        $res = Http::timeout(12)
            ->acceptJson()
            ->asJson()
            ->post(self::API_BASE.'/v1/verify', [
                'merchant' => $merchant,
                'trackId' => $trackId,
            ]);
        if (! $res->successful()) {
            return ['ok' => false, 'message' => $res->body()];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'bad_verify_json'];
        }
        $result = (int) ($data['result'] ?? 0);
        if (! in_array($result, [100, 201], true)) {
            $msg = isset($data['message']) ? (string) $data['message'] : 'verify_failed';

            return ['ok' => false, 'message' => $msg, 'result' => $result];
        }

        return ['ok' => true, 'result' => $result];
    }

    public function handleCallback(Request $request, string $pathSecret): Response
    {
        $want = (string) $this->settings->get('zibal_callback_path_secret', '');
        if ($want === '' || ! hash_equals($want, $pathSecret)) {
            return GatewayHtmlResponse::make('دسترسی غیرمجاز', 'درخواست نامعتبر است.', false);
        }
        $success = trim((string) $request->query('success', ''));
        if ($success !== '1') {
            return GatewayHtmlResponse::make('پرداخت ناموفق', 'پرداخت لغو شد یا ناموفق بود. می‌توانید از ربات دوباره تلاش کنید.', false);
        }
        $trackId = trim((string) $request->query('trackId', ''));
        if ($trackId === '') {
            return GatewayHtmlResponse::make('خطا', 'کد trackId دریافت نشد.', false);
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
        $expected = isset($txMeta['zibal_track_id']) ? (string) $txMeta['zibal_track_id'] : '';
        if ($expected !== '' && ! hash_equals($expected, $trackId)) {
            Log::error('zibal_callback trackId mismatch', ['tx_id' => $txId]);

            return GatewayHtmlResponse::make('خطا', 'کد پرداخت با سفارش مطابقت ندارد.', false);
        }
        $verify = $this->verifyPayment($trackId);
        if (empty($verify['ok'])) {
            Log::error('zibal_callback verify failed', [
                'tx_id' => $txId,
                'message' => (string) ($verify['message'] ?? ''),
            ]);

            return GatewayHtmlResponse::make('خطا در تأیید', 'پرداخت تأیید نشد. در صورت کسر وجه با پشتیبانی تماس بگیرید.', false);
        }
        $orderId = trim((string) $request->query('orderId', ''));
        if ($orderId !== '') {
            $txMeta['zibal_order_id'] = $orderId;
            DB::table('svp_transactions')->where('id', $txId)->update([
                'meta_json' => json_encode($txMeta, JSON_UNESCAPED_UNICODE),
            ]);
        }
        if ((string) $tx->status === 'pending' && ! $this->fulfill->claimPendingTransaction($txId)) {
            return GatewayHtmlResponse::make('پرداخت موفق', 'این سفارش قبلاً تأیید شده است. به ربات برگردید.', true);
        }
        RialFulfillJob::dispatch($txId, 'zibal');

        return GatewayHtmlResponse::make(
            'پرداخت موفق',
            'پرداخت شما با موفقیت ثبت شد. لطفاً به ربات برگردید تا سفارش تکمیل شود.',
            true
        );
    }
}
