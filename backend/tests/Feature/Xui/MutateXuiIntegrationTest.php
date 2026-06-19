<?php

namespace Tests\Feature\Xui;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateXuiIntegrationTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        DB::table('svp_panels')->where('id', 1)->update([
            'panel_api_token' => 'tok',
            'panel_api_flavor' => 'legacy_inbound',
        ]);
        DB::table('svp_services')->where('id', 1)->update([
            'inbound_id' => 1,
            'email' => 'svc@local',
            'xui_client_uuid' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
        ]);
    }

    public function test_service_panel_sync_calls_panel_api(): void
    {
        Http::fake([
            'https://panel.test/panel/api/inbounds/get/1' => Http::response([
                'success' => true,
                'obj' => [
                    'id' => 1,
                    'settings' => json_encode([
                        'clients' => [[
                            'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                            'email' => 'svc@local',
                            'enable' => true,
                            'totalGB' => 0,
                        ]],
                    ]),
                ],
            ]),
            'https://panel.test/panel/api/inbounds/updateClient/*' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);

        $this->actingAsAdmin();
        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'service_panel_sync',
            'service_id' => 1,
        ]);
        $response->assertOk();
        $response->assertJsonPath('ok', true);
    }

    public function test_configs_client_toggle_enable(): void
    {
        Http::fake([
            'https://panel.test/panel/api/inbounds/get/1' => Http::response([
                'success' => true,
                'obj' => [
                    'id' => 1,
                    'settings' => json_encode([
                        'clients' => [[
                            'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee',
                            'email' => 'svc@local',
                            'enable' => false,
                        ]],
                    ]),
                ],
            ]),
            'https://panel.test/panel/api/inbounds/updateClient/*' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);

        $this->actingAsAdmin();
        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'configs_client_toggle_enable',
            'service_id' => 1,
            'enabled' => true,
        ]);
        $response->assertOk();
        $this->assertSame(1, (int) DB::table('svp_services')->where('id', 1)->value('client_enabled'));
    }
}
