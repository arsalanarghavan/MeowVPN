<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutatePolicyTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_forbidden_op(): void
    {
        $this->actingAsReseller();

        $response = $this->postJson('/api/v1/admin/mutate', ['op' => 'settings_tab', 'tab' => 'general']);

        $response->assertForbidden()
            ->assertJson(['ok' => false, 'message' => 'forbidden_op']);
    }

    public function test_reseller_forbidden_perm(): void
    {
        $this->actingAsReseller();
        $reseller = $this->dashboardResellerUser();
        $reseller->permissions_json = [];
        $reseller->save();

        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_status',
            'user_id' => 101,
            'status' => 'approved',
        ]);

        $response->assertForbidden()
            ->assertJson(['ok' => false, 'message' => 'forbidden_perm']);
    }

    public function test_admin_may_run_admin_only_op(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/mutate', ['op' => 'settings_tab', 'tab' => 'general']);

        $response->assertOk()->assertJson(['ok' => true]);
    }
}
