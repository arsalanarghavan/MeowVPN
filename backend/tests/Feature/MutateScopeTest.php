<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateScopeTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_reseller_cannot_mutate_outside_subtree(): void
    {
        $this->actingAsReseller();

        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_status',
            'user_id' => 200,
            'status' => 'blocked',
        ]);

        $response->assertForbidden()
            ->assertJson(['ok' => false, 'message' => 'forbidden_scope']);
    }

    public function test_reseller_may_mutate_downline_user(): void
    {
        $this->actingAsReseller();

        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_status',
            'user_id' => 101,
            'status' => 'approved',
        ]);

        $response->assertOk()->assertJson(['ok' => true]);
    }

    public function test_admin_invalid_reseller_context(): void
    {
        $this->actingAsAdmin();

        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'name' => 'X',
            'panel_id' => 1,
            'reseller_context_svp_user_id' => 999,
        ]);

        $response->assertBadRequest()
            ->assertJson(['ok' => false, 'message' => 'invalid_reseller_context']);
    }

    public function test_reseller_service_scope(): void
    {
        DB::table('svp_services')->insert([
            'id' => 50,
            'user_id' => 200,
            'panel_id' => 1,
            'created_at' => now(),
        ]);

        $this->actingAsReseller();

        $response = $this->postJson('/api/v1/admin/mutate', [
            'op' => 'user_add_days',
            'service_id' => 50,
            'days' => 3,
        ]);

        $response->assertForbidden()
            ->assertJson(['ok' => false, 'message' => 'forbidden_scope']);
    }
}
