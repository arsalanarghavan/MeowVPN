<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Mutate\SettingsTabMutateTest;

/** §15.1 — 17 settings_tab keys + encrypt (v22). */
class SettingsTabKeysBatchTest extends SettingsTabMutateTest
{

    public function test_proxy_tab_encrypts_password_at_rest(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'proxy',
            'telegram_proxy_password' => 'secret-proxy-pass',
        ])->assertOk()->assertJsonPath('ok', true);

        $stored = (string) DB::table('svp_settings')->where('key_name', 'telegram_proxy_password')->value('value');
        $this->assertNotSame('secret-proxy-pass', $stored);
        $this->assertSame('secret-proxy-pass', Crypt::decryptString($stored));
    }
}
