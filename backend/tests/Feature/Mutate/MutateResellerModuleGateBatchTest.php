<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Reseller-module gated ops return module_disabled when reseller module off (v14). */
class MutateResellerModuleGateBatchTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

  /** @var list<string> */
    private const RESELLER_MODULE_OPS = [
        'bot_reseller_delete',
        'bot_reseller_save',
        'bot_reseller_secret_rotate',
        'bot_reseller_toggle_enabled',
        'reseller_backfill_run',
        'reseller_bind_users',
        'bot_reseller_secret_rotate',
        'reseller_bot_tokens_save',
        'reseller_bot_webhook_delete',
        'reseller_bot_webhook_set',
        'reseller_inbound_labels_save',
        'reseller_panel_prices_save',
        'reseller_payment_methods_save',
        'reseller_permissions_save',
        'reseller_wallet_topup_checkout',
        'reseller_wholesale_lines_assign',
            'reseller_dashboard_provision',
        'wholesale_line_delete',
        'wholesale_line_save',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('reseller', false);
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    /** @return array<string, array{0: string}> */
    public static function resellerModuleOpsProvider(): array
    {
        $out = [];
        foreach (self::RESELLER_MODULE_OPS as $op) {
            $out[$op] = [$op];
        }

        return $out;
    }

    /** @dataProvider resellerModuleOpsProvider */
    public function test_reseller_module_op_blocked_when_reseller_off(string $op): void
    {
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
