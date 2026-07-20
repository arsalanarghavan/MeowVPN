<?php

namespace Tests\Feature\XrayCore;

use App\Models\SvpPlan;
use App\Modules\XrayCore\Services\PanelDriverResolver;
use App\Modules\XrayCore\Services\SubscriptionUriBuilder;
use App\Modules\XrayCore\Services\XrayConfigBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class XrayCoreTest extends TestCase
{
    use CreatesSvpTestSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSvpSchema();
        config(['modules.modules.xray_core.enabled' => true]);
    }

    public function test_config_builder_includes_clients_for_native_services(): void
    {
        if (! Schema::hasTable('svp_xray_nodes')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_29_000001_create_xray_core_tables.php']);
        }

        $nodeId = (int) DB::table('svp_xray_nodes')->insertGetId([
            'label' => 'test',
            'public_ip' => '1.2.3.4',
            'agent_url' => 'http://127.0.0.1:8444',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inboundId = (int) DB::table('svp_xray_inbounds')->insertGetId([
            'node_id' => $nodeId,
            'tag' => 'vless-in',
            'protocol' => 'vless',
            'port' => 443,
            'settings_json' => json_encode(['clients' => [], 'decryption' => 'none']),
            'stream_settings_json' => json_encode(['network' => 'tcp', 'security' => 'none']),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('svp_services')->insert([
            'user_id' => 1,
            'panel_id' => $nodeId,
            'panel_driver' => 'native',
            'inbound_id' => $inboundId,
            'xui_client_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'email' => 'u1@test.local',
            'remark' => 'test',
            'client_enabled' => 1,
            'created_at' => now(),
        ]);

        $cfg = app(XrayConfigBuilder::class)->buildForNode($nodeId);
        $inbounds = $cfg['inbounds'] ?? [];
        $this->assertNotEmpty($inbounds);
        $clients = $inbounds[0]['settings']['clients'] ?? [];
        $this->assertCount(1, $clients);
        $this->assertSame('550e8400-e29b-41d4-a716-446655440000', $clients[0]['id']);
    }

    public function test_panel_driver_resolver_native_plan(): void
    {
        if (! Schema::hasTable('svp_xray_inbounds')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_29_000001_create_xray_core_tables.php']);
        }

        $plan = new SvpPlan([
            'panel_driver' => 'native',
            'xray_inbound_ref' => 1,
            'inbound_id' => 0,
        ]);
        $resolver = app(PanelDriverResolver::class);
        $this->assertTrue($resolver->planUsesNative($plan));
        $this->assertSame('native', $resolver->forPlan($plan)->driverType());
    }

    public function test_subscription_uri_builder_vless(): void
    {
        if (! Schema::hasTable('svp_xray_hosts')) {
            $this->artisan('migrate', ['--path' => 'database/migrations/2026_06_29_000001_create_xray_core_tables.php']);
        }

        $nodeId = (int) DB::table('svp_xray_nodes')->insertGetId([
            'label' => 'n',
            'public_ip' => '9.9.9.9',
            'agent_url' => 'http://127.0.0.1:8444',
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $inboundId = (int) DB::table('svp_xray_inbounds')->insertGetId([
            'node_id' => $nodeId,
            'tag' => 'vless',
            'protocol' => 'vless',
            'port' => 443,
            'settings_json' => '{}',
            'stream_settings_json' => json_encode(['network' => 'tcp', 'security' => 'none']),
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('svp_xray_hosts')->insert([
            'inbound_id' => $inboundId,
            'address' => 'edge.example.com',
            'port' => 443,
            'active' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $svc = (object) [
            'panel_driver' => 'native',
            'inbound_id' => $inboundId,
            'xui_client_uuid' => '550e8400-e29b-41d4-a716-446655440000',
            'remark' => 'Test',
        ];
        $uris = app(SubscriptionUriBuilder::class)->buildForService($svc);
        $this->assertNotEmpty($uris);
        $this->assertStringStartsWith('vless://', $uris[0]);
    }
}
