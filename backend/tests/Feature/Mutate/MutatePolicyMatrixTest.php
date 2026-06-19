<?php

namespace Tests\Feature\Mutate;

use App\Services\Mutations\MutatePolicyService;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Data-driven forbidden_perm for all 72 reseller-mapped mutate ops (v16). */
class MutatePolicyMatrixTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    /** @return array<string, array{0: string}> */
    public static function resellerMappedOpsProvider(): array
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

    /** @dataProvider resellerMappedOpsProvider */
    public function test_reseller_without_permissions_gets_forbidden_perm(string $op): void
    {
        $this->actingAsReseller();
        $reseller = $this->dashboardResellerUser();
        $reseller->permissions_json = [];
        $reseller->save();

        $payload = array_merge(['op' => $op], $this->mutatePayloadFor($op));

        $this->postJson('/api/v1/admin/mutate', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_perm');
    }
}
