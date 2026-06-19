<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateRelayAdminNginxSslTest extends TestCase
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
        Http::fake(['relay.test/*' => Http::response(['ok' => true], 200)]);
    }

    /** @param  array<string, mixed>  $extra */
    private function relayOp(string $op, array $extra = []): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(['op' => $op], $extra))
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_admin_dashboard(): void
    {
        $this->relayOp('telegram_relay_admin_dashboard');
    }

    public function test_telegram_relay_admin_domain_add_remove(): void
    {
        $this->relayOp('telegram_relay_admin_domain_add', ['domain' => 'bot.example.com']);
        $this->relayOp('telegram_relay_admin_domain_remove', ['domain' => 'bot.example.com']);
    }

    public function test_telegram_relay_admin_nginx_ops(): void
    {
        $this->relayOp('telegram_relay_admin_nginx_render');
        $this->relayOp('telegram_relay_admin_nginx_test');
        $this->relayOp('telegram_relay_admin_nginx_reload');
    }

    public function test_telegram_relay_admin_ssl_issue_renew(): void
    {
        $this->relayOp('telegram_relay_admin_ssl_issue', ['domain' => 'bot.example.com']);
        $this->relayOp('telegram_relay_admin_ssl_renew', ['domain' => 'bot.example.com']);
    }

    public function test_telegram_relay_admin_service_restart_update_job(): void
    {
        $this->relayOp('telegram_relay_admin_service_restart');
        $this->relayOp('telegram_relay_admin_update');
        $this->relayOp('telegram_relay_admin_job', ['job_id' => 'sync']);
    }

    public function test_telegram_relay_status_domains_sync_auto_sync(): void
    {
        $this->relayOp('telegram_relay_status');
        $this->relayOp('telegram_relay_domains_sync');
        $this->relayOp('telegram_relay_auto_sync');
    }
}
