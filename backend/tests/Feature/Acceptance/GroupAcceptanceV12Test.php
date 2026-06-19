<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 gaps covered in v12 automated tests. */
class GroupAcceptanceV12Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_dashboard_overview_in_state(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=dashboard')
            ->assertOk()
            ->assertJsonStructure(['overview', 'navTabs']);
    }

    public function test_receipts_state_supports_status_filter(): void
    {
        DB::table('svp_receipts')->insert([
            'user_id' => 101,
            'amount' => 5000,
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $rows = $this->actingAsAdmin()
            ->getJson('/api/v1/admin/state?tab=receipts&receipts_status=pending')
            ->assertOk()
            ->json('receipts');

        $this->assertIsArray($rows);
        $this->assertNotEmpty($rows);
    }

    public function test_receipt_aggregates_in_state(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=receipts')
            ->assertOk()
            ->assertJsonStructure(['receiptAggregates']);
    }

    public function test_impersonation_start_requires_admin(): void
    {
        $this->actingAsReseller()->postJson('/api/v1/admin/impersonate/start', [
            'svp_user_id' => 101,
        ])->assertForbidden();
    }

    public function test_monitoring_hosts_in_state(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/admin/state?tab=monitoring')
            ->assertOk()
            ->assertJsonStructure(['monitorHosts']);
    }
}
