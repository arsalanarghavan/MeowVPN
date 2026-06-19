<?php

namespace Tests\Feature\Mutate;

use App\Services\Mutations\MutatePolicyService;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\InteractsWithMutate;
use Tests\Concerns\TogglesModules;
use Tests\TestCase;

/** All 72 reseller-mapped ops — admin smoke ok:true (v16). */
class MutatePolicyPositiveMatrixTest extends TestCase
{
    use InteractsWithMutate;
    use TogglesModules;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
        foreach (['telegram', 'bale', 'xui_panel', 'marketing', 'relay', 'crypto', 'reseller', 'backup'] as $mod) {
            $this->setModuleEnabled($mod, true);
        }
        Http::fake([
            'api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['id' => 1, 'username' => 'bot']]),
        ]);
    }

    /** @return array<string, array{0: string}> */
    public static function mappedOpsProvider(): array
    {
        $policy = new MutatePolicyService;
        $ref = new \ReflectionClass($policy);
        $prop = $ref->getProperty('resellerMap');
        $prop->setAccessible(true);
        /** @var array<string, string> $map */
        $map = $prop->getValue($policy);
        $out = [];
        foreach (array_keys($map) as $op) {
            $out[$op] = [$op];
        }

        return $out;
    }

    /** @dataProvider mappedOpsProvider */
    public function test_mapped_reseller_op_admin_smoke_ok(string $op): void
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
