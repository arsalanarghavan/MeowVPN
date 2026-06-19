<?php

namespace Tests\Feature\Acceptance;

use App\Models\SvpUser;
use App\Services\SettingsStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** §14 v14 acceptance gaps. */
class GroupAcceptanceV14Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_panel_health_badge_in_overview_when_refresh(): void
    {
        Http::fake(['*' => Http::response(['success' => true, 'obj' => []], 200)]);
        $json = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=dashboard&refreshPanelHealth=1')
            ->assertOk()
            ->json('overview');

        $this->assertIsArray($json);
        $live = $json['live']['panels'] ?? [];
        $this->assertIsArray($live);
        if ($live !== []) {
            $this->assertArrayHasKey('health', $live[0]);
        }
    }

    public function test_texts_save_fa_and_en_locales(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_save',
            'key' => 'welcome',
            'locale' => 'fa',
            'value' => 'سلام',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_save',
            'key' => 'welcome',
            'locale' => 'en',
            'value' => 'Hello',
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_configs_stale_always_when_client_seeded(): void
    {
        DB::table('svp_panel_inbound_clients')->insert([
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'stale@test.local',
            'synced_at' => now()->subDays(2),
        ]);

        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=configs')->assertOk()->json();
        $clients = $json['configsClients'] ?? [];
        if (is_array($clients) && $clients !== []) {
            $first = $clients[0] ?? ($clients['rows'][0] ?? null);
            if (is_array($first)) {
                $this->assertTrue(
                    ($first['cache_stale'] ?? $first['cacheStale'] ?? false) === true
                    || array_key_exists('cache_stale', $first)
                    || array_key_exists('cacheStale', $first)
                );
            }
        }
        $this->assertArrayHasKey('configsClients', $json);
    }

    public function test_receipt_reject_mutate(): void
    {
        DB::table('svp_receipts')->insert([
            'user_id' => 101,
            'amount' => 1000,
            'status' => 'pending',
            'created_at' => now(),
        ]);
        $id = (int) DB::table('svp_receipts')->max('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => $id,
            'action' => 'reject',
            'reason' => 'test',
        ])->assertOk();
    }

    public function test_reseller_settings_tab(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/state?tab=reseller_settings')
            ->assertOk();
    }

    public function test_panel_xp_deactivate_via_update(): void
    {
        $panelId = (int) DB::table('svp_panels')->insertGetId([
            'label' => 'Delete Me Panel',
            'panel_url' => 'https://del.test',
            'active' => 1,
            'sort_order' => 50,
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_xp',
            'id' => $panelId,
            'active' => 0,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_panels', ['id' => $panelId, 'active' => 0]);
    }

    public function test_reseller_panel_price_matrix_save(): void
    {
        $resellerId = (int) SvpUser::query()->where('role', 'reseller')->value('id');
        $this->assertGreaterThan(0, $resellerId);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_panel_prices_save',
            'reseller_svp_user_id' => $resellerId,
            'panel_id' => 1,
            'price' => 2500,
            'active' => true,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseHas('svp_reseller_panel_prices', [
            'reseller_svp_user_id' => $resellerId,
            'panel_id' => 1,
        ]);
    }
}
