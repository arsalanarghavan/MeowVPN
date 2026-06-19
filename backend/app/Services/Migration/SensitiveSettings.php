<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Crypt;

class SensitiveSettings
{
    /** @var array<int, string> */
    protected const KEYS = [
        'telegram_bot_token',
        'telegram_token',
        'telegram_webhook_secret',
        'telegram_secret_header',
        'bale_token',
        'bale_webhook_secret',
        'panel_password',
        'panel_api_token',
        'panel_login_secret',
        'portal_link_secret',
        'crypto_nowpayments_api_key',
        'crypto_nowpayments_ipn_secret',
        'crypto_ipn_path_secret',
        'telegram_proxy_password',
        'telegram_relay_shared_secret',
        'bale_wallet_provider_token',
    ];

    public function shouldEncrypt(string $key): bool
    {
        if (in_array($key, self::KEYS, true)) {
            return true;
        }

        return str_contains($key, 'token')
            || str_contains($key, 'secret')
            || str_contains($key, 'password');
    }

    public function encodeValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (is_array($value) || is_object($value)) {
            $plain = json_encode($value, JSON_UNESCAPED_UNICODE);

            return $this->shouldEncrypt($key) ? Crypt::encryptString((string) $plain) : (string) $plain;
        }

        $plain = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
        if ($plain === '') {
            return '';
        }

        return $this->shouldEncrypt($key) ? Crypt::encryptString($plain) : $plain;
    }
}
