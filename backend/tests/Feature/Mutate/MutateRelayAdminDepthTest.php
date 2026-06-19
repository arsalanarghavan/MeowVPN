<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateRelayAdminDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $s = app(SettingsStore::class);
        $s->set('telegram_relay_enabled', true);
        $s->set('telegram_relay_admin_url', 'https://relay.test');
        $s->set('telegram_relay_shared_secret', 'relay-secret');
    }

    public function test_telegram_relay_admin_doctor(): void
    {
        Http::fake(['relay.test/*' => Http::response(['ok' => true, 'checks' => []], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_doctor',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_admin_logs(): void
    {
        Http::fake(['relay.test/*' => Http::response(['ok' => true, 'lines' => []], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_logs',
            'lines' => 50,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_admin_ssl_status(): void
    {
        Http::fake(['relay.test/*' => Http::response(['ok' => true, 'ssl' => []], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_ssl_status',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_rotate_secret(): void
    {
        Http::fake(['relay.test/*' => Http::response(['ok' => true], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_rotate_secret',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
