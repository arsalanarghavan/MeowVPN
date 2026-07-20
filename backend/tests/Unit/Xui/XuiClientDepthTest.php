<?php

namespace Tests\Unit\Xui;

use App\Modules\XuiPanel\Services\XuiClient;
use Tests\TestCase;

class XuiClientDepthTest extends TestCase
{
    public function test_parse_client_ips_response_map_keys(): void
    {
        $client = new XuiClient;
        $ips = $client->parseClientIpsResponse([
            'obj' => [
                '10.0.0.1' => 1234567890,
                '10.0.0.2' => 1234567891,
            ],
        ]);
        $this->assertSame(['10.0.0.1', '10.0.0.2'], $ips);
    }

    public function test_ensure_client_protocol_fields_trojan(): void
    {
        $client = new XuiClient;
        $row = [
            'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
            'email' => 'u@test.local',
        ];
        $this->assertTrue($client->ensureClientProtocolFields($row, 'trojan'));
        $this->assertSame('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee', $row['password']);
    }

    public function test_ensure_client_protocol_fields_shadowsocks(): void
    {
        $client = new XuiClient;
        $row = ['email' => 'ss@test.local'];
        $this->assertTrue($client->ensureClientProtocolFields($row, 'shadowsocks'));
    }

    public function test_resolve_client_path_id_for_update_vless(): void
    {
        $client = new class extends XuiClient
        {
            public function isV3ClientsApi(): bool
            {
                return false;
            }
        };
        $inbound = [
            'protocol' => 'vless',
            'settings' => json_encode([
                'clients' => [
                    ['email' => 'a@test.local', 'id' => '11111111-2222-3333-4444-555555555555'],
                ],
            ]),
        ];
        $key = $client->resolveClientPathIdForUpdate('', $inbound, 'a@test.local');
        $this->assertSame('11111111-2222-3333-4444-555555555555', $key);
    }

    public function test_clients_for_inbound_id_legacy(): void
    {
        $client = new class extends XuiClient
        {
            public function isV3ClientsApi(): bool
            {
                return false;
            }

            public function inboundGet(int $id): ?array
            {
                return [
                    'id' => $id,
                    'settings' => json_encode([
                        'clients' => [
                            ['email' => 'one@test.local'],
                            ['email' => 'two@test.local'],
                        ],
                    ]),
                ];
            }
        };

        $rows = $client->clientsForInboundId(5);
        $this->assertCount(2, $rows);
        $this->assertSame('one@test.local', $rows[0]['email']);
    }

    public function test_probe_get_db_missing_creds(): void
    {
        $client = new XuiClient;
        $probe = $client->runWithPanel(0, fn (XuiClient $x) => $x->probeGetDb(), [
            'panel_url' => 'https://panel.test',
            'panel_api_base' => 'panel/api',
        ]);
        $this->assertFalse($probe['ok']);
        $this->assertSame('missing_cookie_creds', $probe['step']);
    }

    public function test_merge_client_into_inbound_settings(): void
    {
        $client = new XuiClient;
        $inbound = [
            'settings' => json_encode([
                'clients' => [
                    ['email' => 'keep@test.local', 'id' => '11111111-1111-1111-1111-111111111111'],
                    ['email' => 'patch@test.local', 'id' => '22222222-2222-2222-2222-222222222222', 'limitIp' => 1],
                ],
            ]),
        ];
        $merged = $client->mergeClientIntoInboundSettings($inbound, 'patch@test.local', ['limitIp' => 3, 'enable' => true]);
        $this->assertIsArray($merged);
        $this->assertCount(2, $merged['clients']);
        $this->assertSame('keep@test.local', $merged['clients'][0]['email']);
        $this->assertSame(3, $merged['clients'][1]['limitIp']);
        $this->assertTrue($merged['clients'][1]['enable']);
    }

    public function test_build_update_client_settings_payload(): void
    {
        $client = new XuiClient;
        $payload = $client->buildUpdateClientSettingsPayload(['email' => 'a@test.local', 'enable' => true]);
        $this->assertSame([['email' => 'a@test.local', 'enable' => true]], $payload['clients']);
    }

    public function test_response_is_success(): void
    {
        $client = new XuiClient;
        $this->assertTrue($client->responseIsSuccess(['success' => true]));
        $this->assertTrue($client->responseIsSuccess(['obj' => ['deleted' => 2]]));
        $this->assertFalse($client->responseIsSuccess(['success' => false]));
    }

    public function test_get_session_stats(): void
    {
        $client = new XuiClient;
        $stats = $client->getSessionStats();
        $this->assertArrayHasKey('login_attempts', $stats);
        $this->assertArrayHasKey('session_reuse', $stats);
        $this->assertArrayHasKey('probes', $stats);
        $this->assertArrayHasKey('panel_id', $stats);
    }
}
