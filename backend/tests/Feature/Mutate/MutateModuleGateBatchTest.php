<?php

namespace Tests\Feature\Mutate;

use App\Support\MutateOpCatalog;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Batch module_disabled regression for relay (22), xui, and marketing gated ops (v13). */
class MutateModuleGateBatchTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        Http::fake(['*' => Http::response(['ok' => true, 'result' => []], 200)]);
    }

    /** @return array<string, array{0: string}> */
    public static function relayOpsProvider(): array
    {
        return self::opsMatching(fn (string $op) => str_starts_with($op, 'telegram_relay_'));
    }

    /** @return array<string, array{0: string}> */
    public static function xuiOpsProvider(): array
    {
        $xui = [
            'panel_xp', 'panel_test', 'service_panel_sync', 'service_panel_refresh',
            'service_panel_delete_client', 'service_panel_transfer', 'service_apply_canonical_panel_identity',
            'service_regen_key', 'service_regen_sub_id', 'service_set_limit_ip', 'service_alerts_patch',
            'configs_panel_client_patch', 'configs_clients_batch', 'configs_assign_plan',
            'configs_client_toggle_enable', 'configs_client_reset_traffic', 'configs_client_delete',
            'configs_delete_expired_linked', 'inbound_link', 'inbound_autolink',
            'purge_expired_run_cron', 'purge_expired_purge_ready', 'purge_expired_purge_one',
            'panel_economics_save', 'panel_economics_mark_paid', 'shared_economics_save',
            'unit_economics_save', 'unit_economics_config_save',
        ];

        return self::opsMatching(fn (string $op) => in_array($op, $xui, true)
            || str_starts_with($op, 'configs_')
            || str_starts_with($op, 'panel_economics_')
            || str_starts_with($op, 'purge_expired_'));
    }

    /** @return array<string, array{0: string}> */
    public static function marketingOpsProvider(): array
    {
        $marketing = [
            'broadcast_send', 'broadcast_cancel', 'broadcast_run_worker',
            'marketing_rule_save', 'marketing_rule_delete', 'marketing_send_manual', 'marketing_run_rule_now',
        ];

        return self::opsMatching(fn (string $op) => in_array($op, $marketing, true) || str_starts_with($op, 'marketing_'));
    }

    /** @param  callable(string): bool  $filter */
    protected static function opsMatching(callable $filter): array
    {
        $out = [];
        foreach (MutateOpCatalog::all() as $op) {
            if ($filter($op)) {
                $out[$op] = [$op];
            }
        }

        return $out;
    }

    /** @dataProvider relayOpsProvider */
    public function test_relay_op_blocked_when_relay_module_off(string $op): void
    {
        $this->setModuleEnabled('relay', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    /** @dataProvider xuiOpsProvider */
    public function test_xui_op_blocked_when_xui_panel_module_off(string $op): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    /** @dataProvider marketingOpsProvider */
    public function test_marketing_op_blocked_when_marketing_module_off(string $op): void
    {
        $this->setModuleEnabled('marketing', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ))->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_l2tp_update_blocked_when_l2tp_module_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_update',
            'id' => 1,
            'label' => 'x',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_bot_test_bale_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_test_bale',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_bot_test_telegram_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'bot_test_telegram',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_texts_save_blocked_when_telegram_and_bale_off(): void
    {
        $this->setModuleEnabled('telegram', false);
        $this->setModuleEnabled('bale', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'texts_save',
            'key' => 'welcome',
            'value' => 'x',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_l2tp_add_blocked_when_l2tp_module_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_add',
            'label' => 'L2',
            'ssh_host' => '10.0.0.2',
            'l2tp_host' => 'l2tp2.test',
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_l2tp_delete_blocked_when_l2tp_module_off(): void
    {
        $this->setModuleEnabled('l2tp', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'l2tp_delete',
            'id' => 1,
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }

    public function test_user_create_service_blocked_when_xui_panel_off(): void
    {
        $this->setModuleEnabled('xui_panel', false);
        $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', [
            'op' => 'user_create_service',
            'user_id' => 101,
            'panel_id' => 1,
        ])->assertOk()->assertJsonPath('message', 'module_disabled');
    }
}
