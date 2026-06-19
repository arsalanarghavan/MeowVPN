<?php

namespace App\Services;

use Illuminate\Support\Facades\Crypt;

class PanelSecretCipher
{
    public function encrypt(?string $plain): ?string
    {
        $plain = trim((string) $plain);
        if ($plain === '') {
            return null;
        }

        return Crypt::encryptString($plain);
    }

    public function decrypt(?string $stored): string
    {
        $stored = trim((string) $stored);
        if ($stored === '') {
            return '';
        }
        try {
            return Crypt::decryptString($stored);
        } catch (\Throwable) {
            return $stored;
        }
    }
}
