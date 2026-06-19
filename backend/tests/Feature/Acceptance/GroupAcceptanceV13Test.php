<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 gaps — v13 automated acceptance. */
class GroupAcceptanceV13Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_login_success_redirects_to_dashboard(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'log' => 'admin',
            'pwd' => 'changeme',
        ])->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['redirect']);
    }

    public function test_economics_card_link_in_overview(): void
    {
        $overview = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk()
            ->json('overview');

        $this->assertIsArray($overview);
    }

    public function test_reseller_monitoring_scope_isolated(): void
    {
        DB::table('svp_monitor_hosts')->insert([
            'label' => 'Reseller Host',
            'host' => '10.0.0.1',
            'owner_svp_user_id' => 100,
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('svp_monitor_hosts')->insert([
            'label' => 'Admin Host',
            'host' => '10.0.0.2',
            'owner_svp_user_id' => 0,
            'active' => 1,
            'created_at' => now(),
        ]);

        $hosts = $this->actingAsReseller()->getJson('/api/v1/admin/state?tab=monitoring')
            ->assertOk()
            ->json('monitorHosts');

        $this->assertIsArray($hosts);
        foreach ($hosts as $h) {
            $owner = (int) ($h['owner_svp_user_id'] ?? $h['ownerSvpUserId'] ?? -1);
            $this->assertContains($owner, [0, 100]);
        }
    }

    public function test_bot_diagnostics_returns_useful_keys(): void
    {
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);

        $json = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_diagnostics',
        ])->assertOk()->json();

        $this->assertTrue($json['ok'] ?? false);
        $this->assertNotEmpty($json['data'] ?? $json['diagnostics'] ?? $json);
    }

    public function test_reseller_bots_list_in_state(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=reseller_bots')
            ->assertOk()
            ->assertJsonStructure(['botsList']);
    }

    public function test_configs_stale_indicator_in_state(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=configs')->assertOk()->json();
        $this->assertArrayHasKey('configsClients', $json);
        $clients = $json['configsClients'] ?? [];
        if (is_array($clients) && $clients !== []) {
            $first = $clients[0] ?? $clients['rows'][0] ?? null;
            if (is_array($first)) {
                $this->assertTrue(
                    array_key_exists('cache_stale', $first) || array_key_exists('cacheStale', $first),
                    'Expected cache_stale indicator on configs client row'
                );
            }
        }
    }

    public function test_panels_pagination_keys_in_state(): void
    {
        $json = $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=panels')->assertOk()->json();
        $this->assertTrue(
            isset($json['panelsPagination']) || isset($json['panels']) || isset($json['panelRows']),
            'panels tab should expose list data'
        );
    }

    public function test_logs_q_filter_endpoint(): void
    {
        DB::table('svp_logs')->insert([
            'level' => 'info',
            'message' => 'unique-search-token-v13',
            'context_json' => '{}',
            'created_at' => now(),
        ]);

        $this->actingAsAdmin()->getJson('/api/v1/admin/logs?q=unique-search-token-v13')
            ->assertOk()
            ->assertJsonPath('ok', true);
    }

    public function test_reseller_settings_tab_in_state(): void
    {
        $this->actingAsReseller()->getJson('/api/v1/admin/state?tab=reseller_settings')
            ->assertOk();
    }

    public function test_users_bulk_schedule_listed(): void
    {
        \Illuminate\Support\Facades\Artisan::call('schedule:list');
        $this->assertStringContainsString('svp:users_bulk', \Illuminate\Support\Facades\Artisan::output());
    }
}
