<?php

namespace Tests\Feature\Xui;

use App\Modules\XuiPanel\Services\XuiClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class XuiClientTest extends TestCase
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
            'panel_api_flavor' => 'legacy_inbound',
            'active' => 1,
            'created_at' => now(),
        ]);
    }

    public function test_login_and_inbound_get_with_bearer_token(): void
    {
        Http::fake([
            'https://panel.test/panel/api/inbounds/get/1' => Http::response([
                'success' => true,
                'obj' => ['id' => 1, 'remark' => 'main', 'settings' => json_encode(['clients' => []])],
            ]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);
        DB::table('svp_panels')->where('id', 1)->update(['panel_api_token' => 'test-token']);

        $xui = app(XuiClient::class);
        $inbound = $xui->runWithPanel(1, fn () => $xui->inboundGet(1));

        $this->assertIsArray($inbound);
        $this->assertSame(1, (int) ($inbound['id'] ?? 0));
    }

    public function test_count_onlines_response(): void
    {
        $xui = app(XuiClient::class);
        $json = ['success' => true, 'obj' => ['a@x.com', 'b@x.com']];
        $this->assertSame(2, $xui->countOnlinesResponse($json));
    }

    public function test_add_client_legacy_payload(): void
    {
        Http::fake([
            'https://panel.test/panel/api/inbounds/addClient' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);
        DB::table('svp_panels')->where('id', 1)->update(['panel_api_token' => 'tok']);

        $xui = app(XuiClient::class);
        $result = $xui->runWithPanel(1, function () use ($xui) {
            return $xui->addClientRequest([
                'id' => 1,
                'settings' => json_encode(['clients' => [['id' => 'uuid-1', 'email' => 'u1@svp.local']]]),
            ]);
        });

        $this->assertTrue($result['ok']);
    }
}
