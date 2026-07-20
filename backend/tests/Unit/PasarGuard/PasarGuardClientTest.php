<?php

namespace Tests\Unit\PasarGuard;

use App\Modules\PasarGuard\Services\PasarGuardClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class PasarGuardClientTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_panels')->insert([
            'id' => 10,
            'label' => 'PG Test',
            'panel_url' => 'https://pg.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_base' => 'api',
            'panel_provider' => 'pasarguard',
            'panel_api_flavor' => 'pasarguard_v5',
            'panel_template_required' => 0,
            'active' => 1,
            'created_at' => now(),
        ]);
    }

    public function test_login_and_groups_list_as_inbounds(): void
    {
        Http::fake([
            'https://pg.test/api/admin/token' => Http::response([
                'access_token' => 'jwt-test-token',
                'token_type' => 'bearer',
            ], 200),
            'https://pg.test/api/groups/simple*' => Http::response([
                'groups' => [
                    ['id' => 3, 'name' => 'Main Group'],
                    ['id' => 7, 'name' => 'Extra'],
                ],
            ], 200),
            'https://pg.test/api/*' => Http::response(['ok' => true], 200),
        ]);

        $client = app(PasarGuardClient::class);
        $list = $client->runWithPanel(10, function (PasarGuardClient $pg) {
            $this->assertTrue($pg->loginWithRetries(2, 1000));

            return $pg->inboundsList();
        });

        $this->assertIsArray($list);
        $this->assertCount(2, $list);
        $this->assertSame(3, (int) ($list[0]['id'] ?? 0));
        $this->assertSame('Main Group', (string) ($list[0]['remark'] ?? ''));
        $this->assertSame('pasarguard', (string) ($list[0]['protocol'] ?? ''));
    }

    public function test_user_create_and_normalize(): void
    {
        Http::fake([
            'https://pg.test/api/admin/token' => Http::response(['access_token' => 'tok'], 200),
            'https://pg.test/api/user' => Http::response([
                'id' => 42,
                'username' => 'u1@svp.local',
                'status' => 'active',
                'data_limit' => 1073741824,
                'used_traffic' => 0,
                'expire' => '2030-01-01T00:00:00Z',
                'subscription_url' => 'https://pg.test/sub/abc123token',
                'group_ids' => [3],
                'note' => 'test',
                'hwid_limit' => 2,
            ], 200),
            'https://pg.test/api/*' => Http::response(['ok' => true], 200),
        ]);

        $client = app(PasarGuardClient::class);
        $out = $client->runWithPanel(10, function (PasarGuardClient $pg) {
            $this->assertTrue($pg->login());

            return $pg->userCreate([
                'email' => 'u1@svp.local',
                'enable' => true,
                'totalGB' => 1,
                'expiryTime' => strtotime('2030-01-01 UTC') * 1000,
                'limitIp' => 2,
                'comment' => 'test',
            ], [3], 0);
        });

        $this->assertTrue($out['ok']);
        $this->assertIsArray($out['client'] ?? null);
        $this->assertSame('42', (string) ($out['client']['id'] ?? ''));
        $this->assertSame('u1@svp.local', (string) ($out['client']['email'] ?? ''));
        $this->assertSame('abc123token', (string) ($out['client']['subId'] ?? ''));
        $this->assertTrue($client->isLikelyPanelClientId('42'));
        $this->assertFalse($client->isLikelyPanelClientId('not-a-id'));
    }

    public function test_test_connection_reports_provider(): void
    {
        Http::fake([
            'https://pg.test/api/admin/token' => Http::response(['access_token' => 'tok'], 200),
            'https://pg.test/api/system/stats' => Http::response([
                'cpu_usage' => 1.5,
                'online_users' => 2,
            ], 200),
            'https://pg.test/api/groups/simple*' => Http::response(['groups' => []], 200),
            'https://pg.test/api/*' => Http::response(['ok' => true], 200),
        ]);

        $client = app(PasarGuardClient::class);
        $panel = (array) DB::table('svp_panels')->where('id', 10)->first();
        $result = $client->testConnection($panel);

        $this->assertTrue($result['ok']);
        $this->assertSame('pasarguard', $result['provider'] ?? '');
        $this->assertSame('pasarguard_v5', $result['flavor'] ?? '');
    }

    public function test_panel_json_msg_parses_fastapi_detail(): void
    {
        $client = new PasarGuardClient;
        $msg = $client->panelJsonMsg([
            'detail' => [
                ['loc' => ['body', 'username'], 'msg' => 'field required', 'type' => 'value_error'],
            ],
        ]);
        $this->assertStringContainsString('username', $msg);
        $this->assertStringContainsString('field required', $msg);
    }

    public function test_client_sub_links_v3_builds_url(): void
    {
        $client = app(PasarGuardClient::class);
        $links = $client->runWithPanel(10, fn (PasarGuardClient $pg) => $pg->clientSubLinksV3('tok123'));
        $this->assertSame(['https://pg.test/sub/tok123'], $links);
    }

    public function test_client_ips_stubs_not_supported(): void
    {
        $client = new PasarGuardClient;
        $res = $client->clientIps('user@test.local');
        $this->assertFalse($res['ok']);
        $this->assertSame([], $client->parseClientIpsResponse($res));
        $this->assertTrue($client->clientClearIpsV3('user@test.local')['ok']);
    }

    public function test_fetch_client_identity_from_panel(): void
    {
        Http::fake([
            'https://pg.test/api/admin/token' => Http::response(['access_token' => 'tok'], 200),
            'https://pg.test/api/user/by-username/*' => Http::response([
                'id' => 99,
                'username' => 'id@test.local',
                'status' => 'active',
                'subscription_url' => 'https://pg.test/sub/abc99',
                'group_ids' => [3],
            ], 200),
            'https://pg.test/api/*' => Http::response(['ok' => true], 200),
        ]);

        $client = app(PasarGuardClient::class);
        $ident = $client->runWithPanel(10, function (PasarGuardClient $pg) {
            $this->assertTrue($pg->login());

            return $pg->fetchClientIdentityFromPanel('id@test.local');
        });

        $this->assertIsArray($ident);
        $this->assertSame('99', $ident['id']);
        $this->assertSame('abc99', $ident['subId']);
    }

    public function test_clients_del_depleted_not_supported(): void
    {
        $client = new PasarGuardClient;
        $res = $client->clientsDelDepletedV3();
        $this->assertFalse($res['ok']);
    }

    public function test_clients_del_orphans_not_supported(): void
    {
        $client = new PasarGuardClient;
        $res = $client->clientsDelOrphansV3();
        $this->assertFalse($res['ok']);
        $this->assertSame('del_orphans_not_supported', $res['msg']);
    }

    public function test_get_config_json(): void
    {
        Http::fake([
            'https://pg.test/api/admin/token' => Http::response(['access_token' => 'tok'], 200),
            'https://pg.test/api/settings' => Http::response(['subscription_url' => 'https://pg.test/sub'], 200),
            'https://pg.test/api/*' => Http::response(['ok' => true], 200),
        ]);

        $client = app(PasarGuardClient::class);
        $cfg = $client->runWithPanel(10, function (PasarGuardClient $pg) {
            $this->assertTrue($pg->login());

            return $pg->getConfigJson();
        });

        $this->assertIsArray($cfg);
        $this->assertSame('https://pg.test/sub', $cfg['subscription_url'] ?? null);
    }

    public function test_get_session_stats(): void
    {
        $client = app(PasarGuardClient::class);
        $stats = $client->runWithPanel(10, fn (PasarGuardClient $pg) => $pg->getSessionStats());
        $this->assertSame(10, $stats['panel_id']);
        $this->assertArrayHasKey('session_reuse', $stats);
    }

    public function test_pasarguard_bulk_attach_detach_and_unsupported_ops(): void
    {
        Http::fake([
            'https://pg.test/api/admin/token' => Http::response(['access_token' => 'tok'], 200),
            'https://pg.test/api/user/by-username/*' => Http::response([
                'id' => 1,
                'username' => 'u@test',
                'status' => 'active',
                'group_ids' => [3],
            ], 200),
            'https://pg.test/api/user/by-id/*' => Http::response(['ok' => true], 200),
            'https://pg.test/api/*' => Http::response(['ok' => true], 200),
        ]);

        $client = app(PasarGuardClient::class);
        $client->runWithPanel(10, function (PasarGuardClient $pg) {
            $this->assertTrue($pg->login());
            $attach = $pg->clientsBulkAttachV3(['u@test'], [3, 7]);
            $this->assertTrue($attach['ok']);
            $detach = $pg->clientsBulkDetachV3(['u@test'], [7]);
            $this->assertTrue($detach['ok']);
            $this->assertSame('inbound_update_not_supported', $pg->inboundUpdate(['id' => 1])['msg'] ?? '');
            $this->assertSame('inbound_delete_not_supported', $pg->inboundDelete(1)['msg'] ?? '');
            $this->assertFalse($pg->importDbFromPath('/tmp/nope.db')['ok']);
            $this->assertFalse($pg->getDbBinary());
        });
    }
}
