<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth smoke — batch 2 (v18). */
class MutateDepthBatchV18Part2Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('relay', true);
        $this->setModuleEnabled('xui_panel', true);
        app(SettingsStore::class)->merge([
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'relay-secret',
        ]);
        Http::fake(['relay.test/*' => Http::response(['ok' => true], 200)]);
    }

    public function test_telegram_relay_set_webhook_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_set_webhook',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_rotate_secret_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_rotate_secret',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_panel_xp_create_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'name' => 'New Panel',
            'url' => 'https://new-panel.test',
            'username' => 'admin',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    /** v19 relay depth */
    public function test_telegram_relay_status_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_status',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_sync_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_sync',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_admin_doctor_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_doctor',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_bot_set_and_delete_webhook_mutate(): void
    {
        $this->setModuleEnabled('telegram', true);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_set_webhook',
            'platform' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_delete_webhook',
            'platform' => 'telegram',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    /** v19 user_service depth */
    public function test_user_service_toggle_enable_mutate(): void
    {
        $this->setModuleEnabled('xui_panel', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_service_toggle_enable',
            'service_id' => 1,
            'enable' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_service_panel_sync_mutate(): void
    {
        $this->setModuleEnabled('xui_panel', true);
        Http::fake(['https://panel.test/*' => Http::response(['success' => true, 'obj' => []], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_sync',
            'service_id' => 1,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_user_renew_service_mutate(): void
    {
        $this->setModuleEnabled('xui_panel', true);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_renew_service',
            'service_id' => 1,
            'mode' => 'free',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
