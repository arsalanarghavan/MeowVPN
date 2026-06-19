<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\DB;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

class MutateMiscInboundTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    public function test_membership_force_join_prompt(): void
    {
        app(\App\Services\SettingsStore::class)->set('force_join_enabled', true);
        app(\App\Services\SettingsStore::class)->set('force_join_channel_id', '-100123');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'membership',
            'user_id' => 101,
        ])->assertOk()->assertJsonPath('ok', true);
    }

    public function test_inbound_link_and_autolink(): void
    {
        $svcId = (int) DB::table('svp_services')->min('id');

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'inbound_autolink',
            'service_id' => $svcId,
            'panel_id' => 1,
        ])->assertOk();

        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'inbound_link',
            'service_id' => $svcId,
            'panel_id' => 1,
            'inbound_id' => 1,
        ])->assertOk();
    }

    public function test_removed_link_wp_user_unknown_op(): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'link_wp_user',
            'svp_user_id' => 101,
            'wp_user_id' => 555,
        ])->assertStatus(422)->assertJsonPath('message', 'unknown_op');
    }
}
