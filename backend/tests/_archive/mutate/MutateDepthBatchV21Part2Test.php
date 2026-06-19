<?php

namespace Tests\Feature\Mutate;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** Admin depth — v21 part 2 (15 ops). */
class MutateDepthBatchV21Part2Test extends TestCase
{
    use InteractsWithMutate;
    use RefreshDatabase;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        $this->setModuleEnabled('relay', true);
        $this->setModuleEnabled('xui_panel', true);
        Http::fake(['*' => Http::response(['ok' => true], 200)]);
    }

    /** @return array<string, array{0: string}> */
    public static function depthOpsProvider(): array
    {
        $ops = [
            'user_service_transfer', 'service_panel_sync', 'configs_clients_batch',
            'telegram_relay_admin_nginx_render', 'telegram_relay_sync', 'telegram_relay_test',
            'telegram_proxy_test', 'service_regen_key', 'user_renew_service', 'user_add_volume',
            'user_reduce_volume', 'service_set_note', 'service_panel_refresh', 'user_set_role',
            'reseller_permissions_save',
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
