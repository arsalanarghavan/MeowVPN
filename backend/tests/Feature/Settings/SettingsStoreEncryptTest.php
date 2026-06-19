<?php

namespace Tests\Feature\Settings;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class SettingsStoreEncryptTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
    }

    public function test_sensitive_token_encrypted_at_rest(): void
    {
        $store = app(SettingsStore::class);
        $store->set('telegram_bot_token', 'secret-token-123');

        $raw = DB::table('svp_settings')->where('key_name', 'telegram_bot_token')->value('value');
        $this->assertNotSame('secret-token-123', $raw);
        $this->assertSame('secret-token-123', $store->get('telegram_bot_token'));
        $this->assertSame('secret-token-123', Crypt::decryptString((string) $raw));
    }

    public function test_non_sensitive_values_remain_plain(): void
    {
        $store = app(SettingsStore::class);
        $store->set('site_name', 'My Bot');

        $raw = DB::table('svp_settings')->where('key_name', 'site_name')->value('value');
        $this->assertSame('My Bot', $raw);
        $this->assertSame('My Bot', $store->get('site_name'));
    }
}
