<?php

namespace Tests\Feature\Secrets;

use App\Services\PanelSecretCipher;
use Tests\TestCase;

class PanelSecretCipherTest extends TestCase
{
    public function test_encrypt_decrypt_roundtrip(): void
    {
        $cipher = app(PanelSecretCipher::class);
        $enc = $cipher->encrypt('panel-secret-123');
        $this->assertNotSame('panel-secret-123', $enc);
        $this->assertSame('panel-secret-123', $cipher->decrypt($enc));
    }

    public function test_decrypt_legacy_plaintext(): void
    {
        $cipher = app(PanelSecretCipher::class);
        $this->assertSame('legacy-plain', $cipher->decrypt('legacy-plain'));
    }
}
