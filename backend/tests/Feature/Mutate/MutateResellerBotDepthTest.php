<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

class MutateResellerBotDepthTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_bot_reseller_save_and_toggle(): void
    {
        $save = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_reseller_save',
            'reseller_svp_user_id' => 100,
            'platform' => 'telegram',
            'bot_username' => 'reseller_bot',
        ])->assertOk()->assertJsonPath('ok', true);

        $botId = (int) ($save->json('data.id') ?? 0);
        if ($botId < 1) {
            $botId = (int) DB::table('svp_reseller_bot_profiles')->where('reseller_svp_user_id', 100)->value('id');
        }
        $this->assertGreaterThan(0, $botId);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_reseller_toggle_enabled',
            'bot_id' => $botId,
            'enabled' => false,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_reseller_bot_webhook_set_and_delete(): void
    {
        $botId = (int) DB::table('svp_reseller_bot_profiles')->insertGetId([
            'reseller_svp_user_id' => 100,
            'platform' => 'telegram',
            'bot_username' => 'wh_test',
            'enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_bot_webhook_set',
            'bot_id' => $botId,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_bot_webhook_delete',
            'bot_id' => $botId,
        ])->assertOk();
    }

    public function test_bot_reseller_secret_rotate(): void
    {
        $botId = (int) DB::table('svp_reseller_bot_profiles')->insertGetId([
            'reseller_svp_user_id' => 100,
            'platform' => 'telegram',
            'bot_username' => 'sec_bot',
            'enabled' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_reseller_secret_rotate',
            'bot_id' => $botId,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_bot_reseller_delete(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_reseller_save',
            'reseller_svp_user_id' => 100,
            'platform' => 'telegram',
            'bot_username' => 'del_bot',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_reseller_delete',
            'reseller_svp_user_id' => 100,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_reseller_bot_profiles', ['reseller_svp_user_id' => 100]);
    }

    public function test_reseller_bot_tokens_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_bot_tokens_save',
            'reseller_svp_user_id' => 100,
            'telegram_token' => '123:TESTTOKEN',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_telegram_relay_set_webhook_reseller_when_relay_off_returns_error(): void
    {
        $this->setModuleEnabled('relay', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_set_webhook_reseller',
            'reseller_svp_user_id' => 100,
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_telegram_relay_set_webhook_reseller_happy_path(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'relay.test/*' => \Illuminate\Support\Facades\Http::response(['ok' => true, 'url' => 'https://tg.test/r'], 200),
        ]);
        app(\App\Services\SettingsStore::class)->merge([
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'relay-secret',
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_set_webhook_reseller',
            'reseller_svp_user_id' => 100,
        ])->assertOk()->assertJsonPath('ok', true);
    }
}
