<?php

namespace Tests\Feature\Mutate;

use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v23 part 3 (24 ops). */
class MutateDepthBatchV23Part3Test extends TestCase
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
            'service_delete',
            'service_panel_delete_client',
            'service_panel_transfer',
            'service_regen_sub_id',
            'service_set_limit_ip',
            'settings_tab',
            'telegram_relay_admin_dashboard',
            'telegram_relay_admin_doctor',
            'telegram_relay_admin_domain_add',
            'telegram_relay_admin_domain_remove',
            'telegram_relay_admin_job',
            'telegram_relay_admin_logs',
            'telegram_relay_admin_nginx_reload',
            'telegram_relay_admin_nginx_test',
            'telegram_relay_admin_service_restart',
            'telegram_relay_admin_ssl_issue',
            'telegram_relay_admin_ssl_renew',
            'telegram_relay_admin_ssl_status',
            'telegram_relay_admin_update',
            'telegram_relay_auto_sync',
            'telegram_relay_domains_sync',
            'telegram_relay_rotate_secret',
            'telegram_relay_set_webhook',
            'telegram_relay_set_webhook_reseller',
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
