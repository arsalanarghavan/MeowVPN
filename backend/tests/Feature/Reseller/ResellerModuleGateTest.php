<?php

namespace Tests\Feature\Reseller;

use App\Models\DashboardUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ResellerModuleGateTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        config(['modules.modules.reseller.enabled' => false]);
        $this->app->forgetInstance(\App\Modules\ModuleManager::class);
    }

    public function test_discount_save_strips_owner_when_reseller_module_disabled(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'discount_save',
            'code' => 'OFF10',
            'owner_svp_user_id' => 100,
            'percent' => 10,
            'enabled' => true,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('svp_discount_codes', [
            'code' => 'OFF10',
            'owner_svp_user_id' => 0,
        ]);
    }

    public function test_reseller_webhook_route_503_when_module_off(): void
    {
        $this->postJson('/api/v1/webhook/telegram/reseller/100/secret')
            ->assertStatus(503)
            ->assertJsonPath('message', 'module_missing');
    }

    public function test_reseller_dashboard_provision_blocked_when_module_off(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'reseller_dashboard_provision',
            'username' => 'newres',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
