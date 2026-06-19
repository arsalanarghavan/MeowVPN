<?php

namespace Tests\Feature\Mutate;

use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth smoke — batch 2 ops without dedicated depth file (v17). */
class MutateDepthBatchV17Part2Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('telegram', true);
        $this->setModuleEnabled('relay', true);
        $this->setModuleEnabled('xui_panel', true);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
    }

    public function test_force_join_publish_mutate(): void
    {
        app(SettingsStore::class)->merge([
            'force_join_enabled' => true,
            'force_join_channel_id' => '-100123',
            'telegram_bot_token' => '1:abc',
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'force_join_publish',
            'text' => 'Join us',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_unit_economics_config_save_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'unit_economics_config_save',
            'usd_rate' => 60000,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_unit_economics_save_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'unit_economics_save',
            'panel_id' => 1,
            'server_cost_monthly' => 1000000,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_admin_ssl_issue_mutate(): void
    {
        app(SettingsStore::class)->merge([
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'relay-secret',
        ]);
        Http::fake(['relay.test/*' => Http::response(['ok' => true], 200)]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_ssl_issue',
            'domain' => 'bot.example.com',
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
