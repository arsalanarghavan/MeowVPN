<?php

namespace Tests\Feature\Acceptance;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 extended acceptance coverage (groups A–H gaps). */
class GroupAcceptanceExtendedTest extends TestCase
{
    use CreatesSvpTestSchema;
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->createSvpTestSchema();
    }

    public function test_overview_counts_real_data(): void
    {
        $this->actingAsAdmin();
        $res = $this->getJson('/api/v1/admin/state?tab=dashboard')->assertOk()->json('overview');
        $this->assertIsArray($res);
        $this->assertArrayHasKey('users_total', $res);
        $this->assertGreaterThanOrEqual(1, (int) ($res['users_total'] ?? 0));
        $this->assertArrayHasKey('panels_total', $res);
        $this->assertArrayHasKey('receipts_pending', $res);
    }

    public function test_refresh_panel_health_query_accepted(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/state?tab=dashboard&refreshPanelHealth=1')
            ->assertOk()
            ->assertJsonStructure(['overview']);
    }

    public function test_reseller_forbidden_tab_returns_403(): void
    {
        $this->actingAsReseller();
        $this->getJson('/api/v1/admin/state?activeTab=site_settings')
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_tab');
    }

    public function test_telegram_proxy_test_mutate(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true], 200)]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_proxy_test',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_purge_expired_purge_one_mutate(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_purge_one',
            'service_id' => 99999,
        ])->assertOk();
    }

    public function test_crypto_settings_encrypts_sensitive_keys(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'crypto_settings',
            'crypto_nowpayments_api_key' => 'np-test-key',
        ])->assertOk()->assertJsonPath('ok', true);

        $stored = (string) DB::table('svp_settings')->where('key_name', 'crypto_nowpayments_api_key')->value('value');
        $this->assertNotSame('np-test-key', $stored);
        $decoded = json_decode($stored, true);
        $cipher = is_string($decoded) ? $decoded : $stored;
        $this->assertSame('np-test-key', Crypt::decryptString($cipher));
    }

    public function test_logs_filter_by_level(): void
    {
        DB::table('svp_logs')->insert([
            'level' => 'error',
            'message' => 'test error log',
            'created_at' => now(),
        ]);
        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/logs?level=error&limit=5')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_bot_test_telegram_mutate(): void
    {
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => []], 200)]);
        app(SettingsStore::class)->set('telegram_bot_token', '123:ABC');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_test_telegram',
        ])->assertOk();
    }

    public function test_texts_reset_all(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_reset',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_panel_economics_mark_paid_mutate(): void
    {
        DB::table('svp_panels')->insert([
            'id' => 50,
            'label' => 'P50',
            'panel_url' => 'https://p50.test',
            'active' => 1,
            'sort_order' => 0,
            'created_at' => now(),
        ]);
        DB::table('svp_panel_economics_lines')->insert([
            'panel_id' => 50,
            'label' => 'hosting',
            'amount' => 100,
            'active' => 1,
            'expires_at' => now()->addDays(3)->toDateString(),
            'created_at' => now(),
        ]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_economics_mark_paid',
            'panel_id' => 50,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_discount_save_and_delete(): void
    {
        $save = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'discount_save',
            'code' => 'TEST10',
            'percent' => 10,
            'active' => 1,
        ])->assertOk()->assertJsonPath('ok', true);

        $id = (int) $save->json('data.id');
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'discount_delete',
            'id' => $id,
        ])->assertOk();
    }

    public function test_unit_economics_save(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'unit_economics_config_save',
            'margin_pct' => 20,
        ])->assertOk();
    }

    public function test_user_detail_includes_activity_array(): void
    {
        $uid = (int) SvpUser::query()->min('id');
        $this->actingAsAdmin();
        $this->getJson("/api/v1/admin/user/{$uid}")
            ->assertOk()
            ->assertJsonStructure(['activity']);
    }

    public function test_audit_filter_event_type(): void
    {
        $this->actingAsAdmin();
        $this->getJson('/api/v1/admin/audit?domain=mutate&event_type=impersonation.start&limit=5')
            ->assertOk()
            ->assertJsonStructure(['rows', 'pagination']);
    }

    public function test_relay_doctor_mutate(): void
    {
        Http::fake(['relay.test/*' => Http::response(['ok' => true], 200)]);
        app(SettingsStore::class)->merge([
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'sec',
        ]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'telegram_relay_admin_doctor',
        ])->assertOk();
    }

    public function test_settings_tab_rejects_unknown_tab(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'not_a_real_tab',
            'foo' => 'bar',
        ])->assertOk()->assertJsonPath('ok', false);
    }

    public function test_purge_expired_run_cron_returns_stats(): void
    {
        app(SettingsStore::class)->merge([
            'enabled' => true,
            'purge_expired_enabled' => false,
        ]);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'purge_expired_run_cron',
            'force' => true,
        ])->assertOk()->assertJsonStructure(['data' => ['purged', 'warned', 'failed', 'grace']]);
    }

    public function test_health_deep_endpoint(): void
    {
        $this->getJson('/health/deep')->assertOk();
    }

    public function test_settings_tab_bots_allowed(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'settings_tab',
            'tab' => 'bots',
            'enabled' => true,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_reseller_overview_metrics_scoped(): void
    {
        $this->actingAsReseller();
        $overview = $this->getJson('/api/v1/admin/state?tab=dashboard')->assertOk()->json('overview');
        $this->assertIsArray($overview);
        $this->assertArrayHasKey('users_total', $overview);
    }
}
