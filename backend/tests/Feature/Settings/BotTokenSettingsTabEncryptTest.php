<?php

namespace Tests\Feature\Settings;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec v8 item 63 — bot token encrypt via settings_tab */
class BotTokenSettingsTabEncryptTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bots_tab_encrypts_telegram_token(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'bots',
            'telegram_bot_token' => '123456:AAH-test-token',
        ])->assertOk()->assertJsonPath('ok', true);

        $stored = (string) DB::table('svp_settings')->where('key_name', 'telegram_bot_token')->value('value');
        $this->assertNotSame('123456:AAH-test-token', $stored);
        $this->assertSame('123456:AAH-test-token', Crypt::decryptString($stored));
    }
}
