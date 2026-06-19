<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateIntegrationTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_manual_create_balance_service_chain(): void
    {
        $this->actingAsAdmin();

        $create = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_manual_create',
            'username' => 'chainuser',
            'first_name' => 'Chain',
            'status' => 'approved',
        ]);
        $create->assertOk();
        $userId = (int) $create->json('user_id');
        $this->assertGreaterThan(0, $userId);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_balance_delta',
            'user_id' => $userId,
            'delta' => 100,
        ])->assertOk();

        $this->assertSame('100.00', DB::table('svp_users')->where('id', $userId)->value('balance'));

        $svc = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_create_service',
            'user_id' => $userId,
            'panel_id' => 1,
            'email' => 'chain@local',
        ]);
        $svc->assertOk();
        $serviceId = (int) $svc->json('service_id');
        $this->assertGreaterThan(0, $serviceId);

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_add_days',
            'service_id' => $serviceId,
            'days' => 14,
        ])->assertOk();

        $this->assertNotNull(DB::table('svp_services')->where('id', $serviceId)->value('expires_at'));
    }
}
