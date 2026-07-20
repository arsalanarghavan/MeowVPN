<?php

namespace Tests\Feature;

use App\Services\SettingsStore;
use App\Support\MutateOpCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** All 139 ops — admin actor structured response + ok:true when payload valid (v29). */
class MutateSmokeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    /** Ops that intentionally return ok:false by design. */
    private const EXPECT_FAIL_OPS = [
        'panel_merge_execute',
        'configs_panel_del_orphans',
        'configs_bulk_reset_traffic',
        'configs_reset_all_panel_traffic',
        'configs_panel_del_depleted',
        'configs_client_fetch_ips',
        'configs_client_clear_ips',
        'configs_client_set_inbounds',
        'configs_clients_bulk_set_inbounds',
        'configs_inbound_patch',
        'configs_delete_expired_older_than',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['telegram', 'bale', 'xui_panel', 'marketing', 'relay', 'crypto', 'reseller', 'backup', 'l2tp'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        app(SettingsStore::class)->merge([
            'telegram_bot_token' => '1:abc',
            'bale_token' => '2:def',
            'telegram_http_proxy' => 'socks5://127.0.0.1:1080',
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'relay-secret',
        ]);
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'bot']], 200),
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function allOpsProvider(): array
    {
        $skip = array_merge(self::EXPECT_FAIL_OPS, MutateOpCatalog::deprecated());
        $out = [];
        foreach (MutateOpCatalog::all() as $op) {
            if (! in_array($op, $skip, true)) {
                $out[$op] = [$op];
            }
        }

        return $out;
    }

    /** @dataProvider allOpsProvider */
    public function test_mutate_op_admin_ok_true(string $op): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ));

        $response->assertOk();
        $response->assertJsonStructure(['ok']);
        $this->assertTrue($response->json('ok'), "op {$op} failed: ".json_encode($response->json()));
    }

    public function test_removed_link_wp_user_returns_unknown_op(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'link_wp_user',
            'user_id' => 101,
            'wp_user_id' => 999,
        ])->assertStatus(422)->assertJsonPath('message', 'unknown_op');
    }
}
