<?php

namespace Tests\Feature\Relay;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class RelayMutateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();

        $settings = app(SettingsStore::class);
        $settings->set('telegram_relay_enabled', true);
        $settings->set('telegram_relay_admin_url', 'https://relay.test');
        $settings->set('telegram_relay_shared_secret', 'relay-secret');
    }

    public function test_telegram_relay_test_calls_health_endpoint(): void
    {
        Http::fake([
            'relay.test/*' => Http::response(['ok' => true, 'status' => 'up'], 200),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_test',
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/internal/health')
                && $request->header('X-SVP-Relay-Secret')[0] === 'relay-secret';
        });
    }

    public function test_telegram_relay_sync_posts_config_snapshot(): void
    {
        Http::fake([
            'relay.test/*' => Http::response(['ok' => true, 'tenant_id' => 't1'], 200),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_sync',
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/internal/config')
                && isset($request->data()['main']);
        });
    }

    public function test_telegram_relay_set_webhook_syncs_then_sets_webhook(): void
    {
        Http::fake([
            'relay.test/internal/config' => Http::response(['ok' => true], 200),
            'relay.test/internal/set-webhook' => Http::response(['ok' => true, 'url' => 'https://tg.test/webhook'], 200),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_set_webhook',
        ])->assertOk()->assertJsonPath('ok', true);

        Http::assertSent(fn ($r) => str_contains($r->url(), '/internal/set-webhook'));
    }

    public function test_telegram_relay_admin_logs_mutate(): void
    {
        Http::fake(['relay.test/*' => Http::response(['ok' => true, 'lines' => []], 200)]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_logs',
            'lines' => 50,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_rotate_secret_updates_settings(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_rotate_secret',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['secret']);

        $newSecret = (string) app(SettingsStore::class)->get('telegram_relay_shared_secret');
        $this->assertNotSame('', $newSecret);
        $this->assertNotSame('relay-secret', $newSecret);
    }
}
