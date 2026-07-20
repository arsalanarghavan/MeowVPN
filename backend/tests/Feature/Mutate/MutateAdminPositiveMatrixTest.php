<?php

namespace Tests\Feature\Mutate;

use App\Services\Mutations\MutatePolicyService;
use App\Services\SettingsStore;
use App\Support\MutateOpCatalog;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** All admin-only ops (69) — admin actor ok:true (v18). */
class MutateAdminPositiveMatrixTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    /** Ops that intentionally return ok:false by design. */
    private const EXPECT_FAIL_OPS = [
        'panel_merge_execute', // needs services queue; empty panels return ok:false
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['telegram', 'bale', 'xui_panel', 'marketing', 'relay', 'crypto', 'reseller', 'backup', 'l2tp'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        app(SettingsStore::class)->merge([
            'telegram_bot_token' => '1:abc',
            'bale_token' => '2:def',
            'telegram_http_proxy' => 'socks5://127.0.0.1:1080',
            'telegram_relay_enabled' => true,
            'telegram_relay_admin_url' => 'https://relay.test',
            'telegram_relay_shared_secret' => 'relay-secret',
            'force_join_enabled' => true,
            'force_join_channel_id' => '-100123',
        ]);
        Http::fake([
            '*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'bot']], 200),
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function adminOnlyOpsProvider(): array
    {
        $policy = new MutatePolicyService;
        $ref = new \ReflectionClass($policy);
        $prop = $ref->getProperty('resellerMap');
        $prop->setAccessible(true);
        /** @var array<string, string> $map */
        $map = $prop->getValue($policy);
        $resellerOps = array_fill_keys(array_keys($map), true);

        $out = [];
        foreach (MutateOpCatalog::all() as $op) {
            if (! isset($resellerOps[$op]) && ! in_array($op, self::EXPECT_FAIL_OPS, true)) {
                $out[$op] = [$op];
            }
        }

        return $out;
    }

    /** @dataProvider adminOnlyOpsProvider */
    public function test_admin_only_op_admin_actor_smoke_ok(string $op): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/admin/mutate', array_merge(
            ['op' => $op],
            $this->mutatePayloadFor($op),
        ));

        $response->assertOk();
        $this->assertNotSame('forbidden_op', $response->json('message'));
        $this->assertNotSame('forbidden_perm', $response->json('message'));
        $this->assertNotSame('module_disabled', $response->json('message'));
        $this->assertTrue($response->json('ok'), "op {$op} failed: ".json_encode($response->json()));
    }
}
