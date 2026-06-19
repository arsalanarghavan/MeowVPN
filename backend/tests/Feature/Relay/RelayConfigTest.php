<?php

namespace Tests\Feature\Relay;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class RelayConfigTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        app(SettingsStore::class)->set('telegram_relay_shared_secret', 'relay-pull-secret');
    }

    public function test_relay_config_requires_secret_header(): void
    {
        $this->getJson('/api/v1/relay/config')->assertForbidden();
    }

    public function test_relay_config_returns_snapshot_with_valid_secret(): void
    {
        $this->getJson('/api/v1/relay/config', [
            'X-SVP-RELAY-SECRET' => 'relay-pull-secret',
        ])
            ->assertOk()
            ->assertJsonStructure(['main', 'resellers', 'domains', 'laravel_base_url']);
    }
}
