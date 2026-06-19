<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v23 part 1 (24 ops). */
class MutateDepthBatchV23Part1Test extends TestCase
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
            'bot_admin_id_add',
            'bot_admin_id_remove',
            'bot_delete_webhook',
            'bot_reseller_delete',
            'bot_reseller_save',
            'bot_reseller_secret_rotate',
            'bot_reseller_toggle_enabled',
            'bot_test_bale',
            'bot_test_telegram',
            'bot_toggle_enabled',
            'bot_toggle_platform_enabled',
            'bot_ui_layout_reset',
            'bot_ui_layout_save',
            'card_add',
            'card_delete',
            'card_update',
            'configs_assign_plan',
            'configs_client_delete',
            'configs_client_reset_traffic',
            'configs_client_toggle_enable',
            'configs_delete_expired_linked',
            'configs_panel_client_patch',
            'crypto_settings',
            'discount_redemptions',
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
