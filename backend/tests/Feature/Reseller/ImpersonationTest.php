<?php

namespace Tests\Feature\Reseller;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_admin_can_start_and_stop_impersonation(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk()->assertJson(['ok' => true]);

        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('impersonating', true)
            ->assertJsonPath('impersonationTargetId', 100);

        $this->postJson('/api/v1/dashboard/impersonate/stop')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();

        $this->postJson('/api/v1/admin/impersonate/stop')
            ->assertOk()
            ->assertJson(['ok' => true]);

        $this->getJson('/api/v1/bootstrap')
            ->assertOk()
            ->assertJsonPath('impersonating', false);
    }

    public function test_impersonation_scopes_admin_state_to_reseller_downline(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();

        $response = $this->getJson('/api/v1/admin/state?activeTab=users');
        $response->assertOk();
        $ids = collect($response->json('usersList'))->pluck('id')->all();
        $this->assertContains(101, $ids);
        $this->assertNotContains(200, $ids);
    }

    public function test_reseller_cannot_start_impersonation(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertStatus(400);
    }

    public function test_impersonation_writes_audit_log(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/impersonate/start', [
            'targetSvpUserId' => 100,
        ])->assertOk();

        $this->assertDatabaseHas('svp_audit_log', [
            'event_type' => 'impersonation.start',
            'target_id' => 100,
        ]);
    }
}
