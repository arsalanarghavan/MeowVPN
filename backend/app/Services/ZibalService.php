<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZibalService
{
    private string $merchantId;
    private string $baseUrl = 'https://gateway.zibal.ir/v1';
    private string $callbackUrl;

    public function __construct()
    {
        $this->merchantId = config('services.zibal.merchant_id', '');
        $this->callbackUrl = config('services.zibal.callback_url', '');
    }

    /**
     * Request a new payment
     *
     * @param int $amount Amount in Rials
     * @param string $orderId Unique order ID
     * @param string|null $description Payment description
     * @param string|null $mobile User mobile number
     * @return array|null
     */
    public function requestPayment(int $amount, string $orderId, ?string $description = null, ?string $mobile = null): ?array
    {
        if (empty($this->merchantId)) {
            Log::error('Zibal merchant ID not configured');
            return null;
        }

        try {
            $data = [
                'merchant' => $this->merchantId,
                'amount' => $amount,
                'callbackUrl' => $this->callbackUrl,
                'orderId' => $orderId,
            ];

            if ($description) {
                $data['description'] = $description;
            }

            if ($mobile) {
                $data['mobile'] = $mobile;
            }

            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/request", $data);

            if ($response->successful()) {
                $result = $response->json();
                
                if ($result['result'] == 100) {
                    return [
                        'success' => true,
                        'trackId' => $result['trackId'],
                        'paymentUrl' => "https://gateway.zibal.ir/start/{$result['trackId']}",
                    ];
                }

                Log::warning('Zibal request failed', ['result' => $result]);
                return [
                    'success' => false,
                    'error' => $this->getResultMessage($result['result']),
                    'code' => $result['result'],
                ];
            }

            Log::error('Zibal request HTTP error', ['status' => $response->status()]);
            return null;
        } catch (Exception $e) {
            Log::error('Zibal request exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Verify a payment
     *
     * @param string $trackId Track ID from callback
     * @return array|null
     */
    public function verifyPayment(string $trackId): ?array
    {
        if (empty($this->merchantId)) {
            Log::error('Zibal merchant ID not configured');
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/verify", [
                    'merchant' => $this->merchantId,
                    'trackId' => $trackId,
                ]);

            if ($response->successful()) {
                $result = $response->json();
                
                // Result 100 = success, 201 = already verified
                if ($result['result'] == 100 || $result['result'] == 201) {
                    return [
                        'success' => true,
                        'amount' => $result['amount'],
                        'status' => $result['status'],
                        'refNumber' => $result['refNumber'] ?? null,
                        'orderId' => $result['orderId'] ?? null,
                        'cardNumber' => $result['cardNumber'] ?? null,
                        'paidAt' => $result['paidAt'] ?? null,
                        'alreadyVerified' => $result['result'] == 201,
                    ];
                }

                return [
                    'success' => false,
                    'error' => $this->getResultMessage($result['result']),
                    'code' => $result['result'],
                    'status' => $result['status'] ?? null,
                ];
            }

            Log::error('Zibal verify HTTP error', ['status' => $response->status()]);
            return null;
        } catch (Exception $e) {
            Log::error('Zibal verify exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Inquiry about a transaction
     *
     * @param string $trackId Track ID
     * @return array|null
     */
    public function inquiry(string $trackId): ?array
    {
        if (empty($this->merchantId)) {
            return null;
        }

        try {
            $response = Http::timeout(15)
                ->post("{$this->baseUrl}/inquiry", [
                    'merchant' => $this->merchantId,
                    'trackId' => $trackId,
                ]);

            if ($response->successful()) {
                return $response->json();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Zibal inquiry exception: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get human readable result message
     */
    private function getResultMessage(int $code): string
    {
        return match ($code) {
            100 => 'با موفقیت تایید شد',
            102 => 'پذیرنده غیرفعال است',
            103 => 'پذیرنده یافت نشد',
            104 => 'پذیرنده غیرفعال است',
            105 => 'مبلغ کمتر از حد مجاز است',
            106 => 'آدرس بازگشت نامعتبر است',
            113 => 'مبلغ تراکنش بیشتر از سقف مجاز پذیرنده است',
            201 => 'قبلاً تایید شده',
            202 => 'سفارش پرداخت نشده یا ناموفق بوده است',
            203 => 'شناسه پیگیری یافت نشد',
            default => 'خطای نامشخص',
        };
    }

    /**
     * Get transaction status message
     */
    public function getStatusMessage(int $status): string
    {
        return match ($status) {
            -1 => 'در انتظار پرداخت',
            -2 => 'خطای داخلی',
            1 => 'پرداخت شده - تایید شده',
            2 => 'پرداخت شده - تایید نشده',
            3 => 'لغو شده توسط کاربر',
            4 => 'شماره کارت نامعتبر',
            5 => 'موجودی ناکافی',
            6 => 'رمز نادرست',
            7 => 'تعداد درخواست زیاد',
            8 => 'تعداد پرداخت روزانه بیش از حد مجاز',
            9 => 'تعداد پرداخت روزانه اینترنتی بیش از حد مجاز',
            10 => 'کارت نامعتبر',
            11 => 'کارت مسدود شده',
            12 => 'کارت منقضی شده',
            default => 'وضعیت نامشخص',
        };
    }
}

