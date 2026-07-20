<?php

namespace App\Modules\Crypto\Mutations;

use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Crypt;

class CryptoMutations
{
    protected const SENSITIVE_KEYS = [
        'crypto_nowpayments_api_key',
        'crypto_nowpayments_ipn_secret',
        'crypto_tetra_api_key',
    ];

    protected const PATH_SECRET_KEYS = [
        'crypto_ipn_path_secret',
        'crypto_tetra_callback_path_secret',
    ];

    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'crypto_settings' => [self::class, 'cryptoSettings'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function cryptoSettings(array $payload, ?Authenticatable $actor): array
    {
        if (! svp_modules()->isEnabled('crypto')) {
            return svp_err('module_disabled');
        }
        foreach ($payload as $key => $value) {
            if ($key === 'op') {
                continue;
            }
            $k = (string) $key;
            if (in_array($k, self::PATH_SECRET_KEYS, true) && trim((string) $value) === '') {
                $value = bin2hex(random_bytes(16));
            }
            if ($k === 'crypto_tetra_api_key' && trim((string) $value) === '') {
                continue;
            }
            if ($k === 'crypto_nowpayments_api_key' && trim((string) $value) === '') {
                continue;
            }
            if ($k === 'crypto_nowpayments_ipn_secret' && trim((string) $value) === '') {
                continue;
            }
            if (in_array($k, self::SENSITIVE_KEYS, true) && is_string($value) && $value !== '') {
                $value = Crypt::encryptString($value);
            }
            $this->settings->set($k, $value);
        }

        foreach (self::PATH_SECRET_KEYS as $secretKey) {
            if (trim((string) $this->settings->get($secretKey, '')) === '') {
                $this->settings->set($secretKey, bin2hex(random_bytes(16)));
            }
        }

        return svp_ok();
    }
}
