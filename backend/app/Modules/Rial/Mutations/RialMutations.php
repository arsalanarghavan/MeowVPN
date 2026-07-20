<?php

namespace App\Modules\Rial\Mutations;

use App\Services\SettingsStore;
use Illuminate\Contracts\Auth\Authenticatable;

class RialMutations
{
    protected const PATH_SECRET_KEYS = [
        'zarinpal_callback_path_secret',
        'aqayepardakht_callback_path_secret',
        'zibal_callback_path_secret',
    ];

    public function __construct(protected SettingsStore $settings) {}

    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'rial_settings' => [self::class, 'rialSettings'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function rialSettings(array $payload, ?Authenticatable $actor): array
    {
        if (! svp_modules()->isEnabled('rial')) {
            return svp_err('module_disabled');
        }

        $allowed = [
            'zarinpal_merchant_id',
            'zarinpal_sandbox',
            'zarinpal_callback_path_secret',
            'aqayepardakht_pin',
            'aqayepardakht_sandbox',
            'aqayepardakht_callback_path_secret',
            'zibal_merchant',
            'zibal_sandbox',
            'zibal_callback_path_secret',
        ];

        foreach ($payload as $key => $value) {
            if ($key === 'op' || ! in_array((string) $key, $allowed, true)) {
                continue;
            }
            $k = (string) $key;
            if (in_array($k, self::PATH_SECRET_KEYS, true) && trim((string) $value) === '') {
                $value = bin2hex(random_bytes(16));
            }
            if (in_array($k, ['zarinpal_sandbox', 'aqayepardakht_sandbox', 'zibal_sandbox'], true)) {
                $value = ! empty($value) ? 1 : 0;
            }
            if (in_array($k, ['zarinpal_merchant_id', 'aqayepardakht_pin', 'zibal_merchant'], true)
                && trim((string) $value) === '') {
                continue;
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
