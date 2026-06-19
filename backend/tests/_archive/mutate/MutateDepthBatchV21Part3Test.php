<?php

namespace Tests\Feature\Mutate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v21 part 3 (14 ops). */
class MutateDepthBatchV21Part3Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['marketing', 'xui_panel', 'l2tp', 'backup'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    /** @return array<string, array{0: string}> */
    public static function depthOpsProvider(): array
    {
        $ops = [
            'marketing_rule_save', 'marketing_send_manual', 'broadcast_send', 'broadcast_run_worker',
            'l2tp_add', 'l2tp_update', 'unit_economics_save', 'shared_economics_save',
            'panel_economics_save', 'bot_set_webhook', 'bot_diagnostics', 'texts_save',
            'user_merge_preview', 'users_bulk_wallet',
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
