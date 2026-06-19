<?php

namespace Tests\Feature\Mutate;

use Tests\Concerns\InteractsWithMutate;
use Tests\TestCase;

/** marketing.lifecycle permission gate for lifecycle ops (v13). */
class MutateMarketingLifecycleTest extends TestCase
{
    use InteractsWithMutate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMutateFixtures();
    }

    /** @return array<string, array{0: string}> */
    public static function lifecycleOpsProvider(): array
    {
        return [
            'marketing_rule_delete' => ['marketing_rule_delete'],
            'marketing_send_manual' => ['marketing_send_manual'],
            'marketing_run_rule_now' => ['marketing_run_rule_now'],
        ];
    }

    /** @dataProvider lifecycleOpsProvider */
    public function test_reseller_without_lifecycle_perm_blocked(string $op): void
    {
        $this->actingAsReseller();
        $reseller = $this->dashboardResellerUser();
        $reseller->permissions_json = [
            'plans.manage' => true,
            'broadcast.send' => true,
        ];
        $reseller->save();

        $payload = match ($op) {
            'marketing_rule_delete' => ['op' => $op, 'rule_id' => 1],
            'marketing_send_manual' => ['op' => $op, 'segment' => 'never_purchased', 'text' => 'hi'],
            'marketing_run_rule_now' => ['op' => $op, 'rule_id' => 1],
            default => ['op' => $op],
        };

        $this->postJson('/api/v1/admin/mutate', $payload)
            ->assertForbidden()
            ->assertJsonPath('message', 'forbidden_op');
    }

    public function test_reseller_with_lifecycle_perm_blocked_admin_only(): void
    {
        $this->actingAsReseller();
        $reseller = $this->dashboardResellerUser();
        $reseller->permissions_json = [
            'plans.manage' => true,
            'marketing.lifecycle' => true,
        ];
        $reseller->save();

        $this->postJson('/api/v1/admin/mutate', [
            'op' => 'marketing_rule_delete',
            'rule_id' => 999,
        ])->assertForbidden()->assertJsonPath('message', 'forbidden_op');
    }
}
