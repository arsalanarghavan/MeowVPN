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
            if ($k === 'crypto_ipn_path_secret' && trim((string) $value) === '') {
                $value = bin2hex(random_bytes(16));
            }
            if (in_array($k, self::SENSITIVE_KEYS, true) && is_string($value) && $value !== '') {
                $value = Crypt::encryptString($value);
            }
            $this->settings->set($k, $value);
        }

        if (trim((string) $this->settings->get('crypto_ipn_path_secret', '')) === '') {
            $this->settings->set('crypto_ipn_path_secret', bin2hex(random_bytes(16)));
        }

        return svp_ok();
    }
}
