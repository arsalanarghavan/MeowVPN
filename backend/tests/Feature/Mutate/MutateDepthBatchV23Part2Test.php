<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v23 part 2 (24 ops). */
class MutateDepthBatchV23Part2Test extends TestCase
{
    use InteractsWithMutate;
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
            'l2tp_delete',
            'marketing_rule_delete',
            'marketing_run_rule_now',
            'membership',
            'panel_test',
            'plan',
            'purge_expired_purge_one',
            'purge_expired_purge_ready',
            'receipt_action',
            'receipt_set_status',
            'receipt_update',
            'reseller_bind_users',
            'bot_reseller_secret_rotate',
            'reseller_bot_tokens_save',
            'reseller_bot_webhook_delete',
            'reseller_bot_webhook_set',
            'reseller_inbound_labels_save',
            'reseller_panel_prices_save',
            'reseller_payment_methods_save',
            'reseller_wallet_topup_checkout',
            'reseller_wholesale_lines_assign',
            'reseller_dashboard_provision',
            'service_alerts_patch',
            'service_apply_canonical_panel_identity',
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
