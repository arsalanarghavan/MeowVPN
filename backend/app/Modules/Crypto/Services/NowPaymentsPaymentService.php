<?php

namespace App\Modules\Crypto\Services;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class NowPaymentsPaymentService
{
    public function __construct(protected SettingsStore $settings) {}

    public function ipnCallbackUrl(): string
    {
        $path = (string) $this->settings->get('crypto_ipn_path_secret', '');
        if ($path === '') {
            return '';
        }
        $base = rtrim((string) $this->settings->get('public_site_url', config('app.url')), '/');

        return $base.'/api/v1/crypto-ipn/'.rawurlencode($path);
    }

    /** @return array{ok:bool, text?:string, reply_markup?:array<string, mixed>, pay_url?:string, message?:string} */
    public function createInvoice(object $tx, string $platform = 'telegram'): array
    {
        $api = trim((string) $this->settings->get('crypto_nowpayments_api_key', ''));
        if ($api === '') {
            return ['ok' => false, 'message' => 'API key خالی است.'];
        }
        $ipnUrl = $this->ipnCallbackUrl();
        if ($ipnUrl === '') {
            return ['ok' => false, 'message' => 'رمز مسیر IPN خالی است؛ یک‌بار ذخیرهٔ تنظیمات را بزنید.'];
        }
        $rate = (float) $this->settings->get('crypto_toman_per_usd', 50000.0);
        if ($rate < 1.0) {
            $rate = 50000.0;
        }
        $toman = (float) $tx->amount;
        $usd = round($toman / $rate, 2);
        if ($usd < 0.01) {
            $usd = max(0.01, round($toman / $rate, 4));
        }
        $payCurrency = strtolower(preg_replace('/[^a-z0-9]/', '', (string) $this->settings->get('crypto_nowpayments_pay_currency', 'usdttrc20')) ?: 'usdttrc20');
        $txId = (int) $tx->id;
        $res = Http::timeout(12)
            ->withHeaders(['x-api-key' => $api])
            ->asJson()
            ->post('https://api.nowpayments.io/v1/payment', [
                'price_amount' => $usd,
                'price_currency' => 'usd',
                'pay_currency' => $payCurrency,
                'order_id' => (string) $txId,
                'order_description' => 'MeowVPN tx '.$txId,
                'ipn_callback_url' => $ipnUrl,
            ]);
        if (! $res->successful()) {
            $data = $res->json();
            $err = is_array($data) && isset($data['message']) ? (string) $data['message'] : $res->body();

            return ['ok' => false, 'message' => $err !== '' ? $err : 'NOWPayments error'];
        }
        $data = $res->json();
        if (! is_array($data)) {
            return ['ok' => false, 'message' => 'پاسخ نامعتبر از NOWPayments.'];
        }
        $paymentId = isset($data['payment_id']) ? (string) $data['payment_id'] : '';
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);
        $meta = is_array($meta) ? $meta : [];
        if ($paymentId !== '') {
            $meta['nowpayments_payment_id'] = $paymentId;
        }
        $meta['payment_method'] = 'crypto_auto';
        DB::table('svp_transactions')->where('id', $txId)->update([
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);

        $payUrl = '';
        foreach (['invoice_url', 'pay_url'] as $k) {
            if (! empty($data[$k]) && is_string($data[$k])) {
                $payUrl = (string) $data[$k];
                break;
            }
        }
        $addr = isset($data['pay_address']) ? (string) $data['pay_address'] : '';
        $amt = isset($data['pay_amount']) ? (string) $data['pay_amount'] : '';
        $cur = isset($data['pay_currency']) ? (string) $data['pay_currency'] : $payCurrency;
        $text = "₿ پرداخت کریپتو (NOWPayments)\n➖➖➖➖➖➖➖➖\n🆔 سفارش: ".$txId."\n💵 مبلغ سفارش: ".number_format($toman)." تومان\n";
        if ($payUrl !== '') {
            $text .= "\n➡️ لینک پرداخت را باز کنید و طبق راهنما پرداخت را تمام کنید.\n";

            return [
                'ok' => true,
                'text' => $text,
                'reply_markup' => [
                    'inline_keyboard' => [
                        [['text' => '🔗 پرداخت در NOWPayments', 'url' => $payUrl]],
                    ],
                ],
                'pay_url' => $payUrl,
            ];
        }
        if ($addr !== '') {
            $text .= "\n📍 آدرس ولت:\n".$addr."\n";
            if ($amt !== '') {
                $text .= '🔢 مبلغ: '.$amt.' '.$cur."\n";
            }
            $text .= "\n⚠️ شبکه را اشتباه انتخاب نکنید.\n";
            $rows = [];
            if ($platform === 'telegram') {
                $rows[] = [['text' => '📋 کپی آدرس', 'copy_text' => ['text' => $addr]]];
            }

            return [
                'ok' => true,
                'text' => $text,
                'reply_markup' => ['inline_keyboard' => $rows],
            ];
        }

        return ['ok' => false, 'message' => 'لینک یا آدرس پرداخت در پاسخ نبود.'];
    }
}
