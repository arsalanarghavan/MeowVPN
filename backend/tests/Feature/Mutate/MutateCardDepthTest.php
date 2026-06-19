<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateCardDepthTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_card_add_update_delete_reorder(): void
    {
        $add = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'card_add',
            'card_number' => '6037-0000-0000-0001',
            'holder_name' => 'Test',
        ])->assertOk()->assertJsonPath('ok', true);

        $id = (int) $add->json('data.card_id');
        $this->assertGreaterThan(0, $id);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'card_update',
            'id' => $id,
            'label' => 'Updated Card',
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'card_reorder',
            'order' => [$id],
        ])->assertOk()->assertJsonPath('ok', true);

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'card_delete',
            'id' => $id,
        ])->assertOk()->assertJsonPath('ok', true);

        $this->assertDatabaseMissing('svp_cards', ['id' => $id]);
    }
}
