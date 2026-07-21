<?php

namespace Tests\Feature\Xui;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class XuiClientDeleteClientTest extends TestCase
{
    use CreatesSvpTestSchema;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpTestSchema();
        DB::table('svp_panels')->insert([
            'id' => 1,
            'label' => 'Test',
            'panel_url' => 'https://panel.test',
            'panel_username' => 'admin',
            'panel_password' => 'secret',
            'panel_api_base' => 'panel/api',
            'panel_api_flavor' => 'v3_clients',
            'panel_api_token' => 'tok',
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'id' => 50,
            'user_id' => 1,
            'panel_id' => 1,
            'inbound_id' => 1,
            'email' => 'svc@svp.local',
            'xui_client_id' => 'uuid-1',
            'xui_client_uuid' => 'uuid-1',
            'created_at' => now(),
        ]);
    }

    public function test_delete_client_login_fail_returns_panel_login(): void
    {
        $client = new class extends XuiClient
        {
            public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool
            {
                return false;
            }
        };

        $r = $client->deleteClient([], 50);

        $this->assertFalse((bool) ($r['ok'] ?? true));
        $this->assertSame('panel_login', (string) ($r['reason'] ?? ''));
        $row = DB::table('svp_services')->where('id', 50)->first();
        $this->assertSame('uuid-1', (string) ($row->xui_client_uuid ?? ''));
        $this->assertNull($row->deleted_at ?? null);
    }

    public function test_delete_client_v3_absent_returns_panel_absent(): void
    {
        $client = new class extends XuiClient
        {
            public function loginWithRetries(int $maxAttempts = 6, int $delayUs = 350000): bool
            {
                return true;
            }

            public function isV3ClientsApi(): bool
            {
                return true;
            }

            public function clientGetV3(string $email): ?array
            {
                return null;
            }
        };

        $r = $client->deleteClient([], 50);

        $this->assertTrue((bool) ($r['ok'] ?? false));
        $this->assertTrue((bool) ($r['panel_absent'] ?? false));
        $row = DB::table('svp_services')->where('id', 50)->first();
        $this->assertNull($row->xui_client_id);
        $this->assertNull($row->xui_client_uuid);
        $this->assertNull($row->deleted_at ?? null);
    }
}
