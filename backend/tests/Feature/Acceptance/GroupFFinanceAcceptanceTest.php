<?php

namespace Tests\Feature\Acceptance;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Spec §14 Group F — Finance */
class GroupFFinanceAcceptanceTest extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_receipt_action_approve(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'receipt_action',
            'receipt_id' => 1,
            'action' => 'approve',
        ])->assertOk();
    }

    public function test_card_add(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'card_add',
            'label' => 'Test Card',
            'number' => '6037-1234',
        ])->assertOk();
    }

    public function test_panel_economics_save(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'panel_economics_save',
            'panel_id' => 1,
            'monthly_cost' => 100000,
        ])->assertOk();
    }

    public function test_plan_mutate(): void
    {
        $this->actingAsAdmin();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'plan',
            'action' => 'add',
            'name' => 'Acceptance Plan',
            'panel_id' => 1,
            'inbound_id' => 1,
            'traffic_gb' => 10,
            'duration_days' => 30,
        ])->assertOk();
    }

    public function test_receipts_tab_in_state(): void
    {
        $this->actingAsAdmin();

        $this->getJson('/api/v1/admin/state?tab=receipts')
            ->assertOk()
            ->assertJsonStructure(['receipts']);
    }
}
