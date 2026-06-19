<?php

namespace Tests\Feature\Mutate;

use App\Services\Mutations\MutatePolicyService;
use App\Support\MutateOpCatalog;
use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** Admin-only ops must return forbidden_op for reseller (v14). */
class MutateAdminOnlyMatrixTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
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
            if (! isset($resellerOps[$op])) {
                $out[$op] = [$op];
            }
        }

        return $out;
    }

    /** @dataProvider adminOnlyOpsProvider */
    public function test_reseller_gets_forbidden_op(string $op): void
    {
        $this->actingAsReseller();
        $reseller = $this->dashboardResellerUser();
        $reseller->permissions_json = [
            'users.manage' => true,
            'plans.manage' => true,
            'services.manage' => true,
            'users.bulk' => true,
            'broadcast.send' => true,
            'receipts.review' => true,
            'marketing.lifecycle' => true,
        ];
        $reseller->save();

        $payload = array_merge(['op' => $op], $this->mutatePayloadFor($op));

        $this->postJson('/api/v1/admin/mutate', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_op');
    }
}
