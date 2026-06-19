<?php

namespace Tests\Feature\Xui;

use App\Services\Commerce\ServiceProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSvpTestSchema;
use Tests\TestCase;

class ServiceProvisionerTest extends TestCase
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
            'panel_api_token' => 'tok',
            'active' => 1,
            'created_at' => now(),
        ]);
        DB::table('svp_users')->insert(['id' => 101, 'username' => 'u101', 'role' => 'user', 'status' => 'approved', 'created_at' => now()]);
        DB::table('svp_plans')->insert([
            'id' => 1,
            'name' => 'P1',
            'category' => 'normal',
            'panel_id' => 1,
            'inbound_id' => 1,
            'service_type' => 'xray',
            'active' => 1,
            'duration_days' => 30,
            'traffic_gb' => 10,
            'price' => 1000,
            'created_at' => now(),
        ]);
    }

    public function test_provision_fails_when_plan_inactive(): void
    {
        DB::table('svp_plans')->where('id', 1)->update(['active' => 0]);
        $result = app(ServiceProvisioner::class)->createFromPlan(101, 1);
        $this->assertFalse($result['ok']);
        $this->assertSame('plan_missing_or_inactive', $result['reason']);
    }

    public function test_provision_creates_service_on_panel(): void
    {
        $uuid = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
        Http::fake([
            'https://panel.test/panel/api/server/getNewUUID' => Http::response(['success' => true, 'obj' => $uuid]),
            'https://panel.test/panel/api/inbounds/get/1' => Http::sequence()
                ->push(['success' => true, 'obj' => ['id' => 1, 'remark' => 'in', 'settings' => json_encode(['clients' => []])]])
                ->push(['success' => true, 'obj' => [
                    'id' => 1,
                    'remark' => 'in',
                    'settings' => json_encode(['clients' => [[
                        'id' => $uuid,
                        'email' => 'u101@svp.local',
                        'subId' => 'sub123',
                        'enable' => true,
                    ]]]),
                ]]),
            'https://panel.test/panel/api/inbounds/addClient' => Http::response(['success' => true, 'obj' => true]),
            'https://panel.test/panel/api/inbounds/list' => Http::response(['success' => true, 'obj' => []]),
            'https://panel.test/panel/api/*' => Http::response(['success' => true, 'obj' => []]),
        ]);

        $result = app(ServiceProvisioner::class)->createFromPlan(101, 1);
        $this->assertTrue($result['ok'], json_encode($result));
        $this->assertGreaterThan(0, (int) ($result['service_id'] ?? 0));
        $svc = DB::table('svp_services')->where('id', $result['service_id'])->first();
        $this->assertNotNull($svc);
        $this->assertSame('plan', $svc->provision_type);
    }
}
