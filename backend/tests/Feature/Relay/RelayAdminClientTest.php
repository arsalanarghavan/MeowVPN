<?php

namespace Tests\Feature\Relay;

use App\Modules\Relay\Services\RelayAdminClient;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RelayAdminClientTest extends TestCase
{
    use RefreshDatabase;

    public function test_post_uses_x_svp_relay_secret_header(): void
    {
        $settings = app(SettingsStore::class);
        $settings->set('telegram_relay_admin_url', 'https://relay.test');
        $settings->set('telegram_relay_shared_secret', 'top-secret');

        Http::fake([
            'relay.test/*' => Http::response(['ok' => true], 200),
        ]);

        $result = app(RelayAdminClient::class)->post('/internal/domains/sync', ['domains' => ['a.test']]);

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return $request->header('X-SVP-Relay-Secret')[0] === 'top-secret'
                && $request->url() === 'https://relay.test/internal/domains/sync';
        });
    }

    public function test_returns_not_configured_without_url_or_secret(): void
    {
        $result = app(RelayAdminClient::class)->get('/internal/health');

        $this->assertFalse($result['ok']);
        $this->assertSame('relay_not_configured', $result['message']);
    }
}
