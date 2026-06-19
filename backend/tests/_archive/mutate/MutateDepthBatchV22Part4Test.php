<?php

namespace Tests\Feature\Mutate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v22 part 4 (24 ops). */
class MutateDepthBatchV22Part4Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['telegram', 'bale', 'xui_panel', 'marketing', 'relay', 'crypto', 'reseller', 'backup', 'l2tp'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    /** @return array<string, array{0: string}> */
    public static function depthOpsProvider(): array
    {
        $ops = [
            'telegram_relay_status',
            'text_reset_one',
            'texts_reset',
            'unit_economics_config_save',
            'user_add_days',
            'user_admin_message',
            'user_balance_delta',
            'user_create_service',
            'user_manual_create',
            'user_merge',
            'user_reduce_days',
            'user_service_add_slots',
            'user_service_reduce_slots',
            'user_service_toggle_enable',
            'user_set_referrer',
            'user_status',
            'users_bulk_alerts',
            'users_bulk_extend',
            'users_bulk_job_cancel',
            'users_bulk_job_resume',
            'users_bulk_run_worker',
            'users_bulk_slots',
            'users_bulk_volume',
            'wholesale_line_delete',
        ];
        $out = [];
        foreach ($ops as $op) {
            $out[$op] = [$op];
        }
        return $out;
    }

    /** @dataProvider depthOpsProvider */
    public function test_admin_depth_op_ok(string $op): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('ok', true);
    }
}
