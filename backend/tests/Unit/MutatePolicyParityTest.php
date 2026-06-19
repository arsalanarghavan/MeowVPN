<?php

namespace Tests\Unit;

use App\Services\Mutations\MutatePolicyService;
use Tests\TestCase;

class MutatePolicyParityTest extends TestCase
{
    /** @return array<string, string> */
    protected function resellerMap(): array
    {
        $policy = new MutatePolicyService;
        $ref = new \ReflectionClass($policy);
        $prop = $ref->getProperty('resellerMap');
        $prop->setAccessible(true);

        return $prop->getValue($policy);
    }

    public function test_reseller_map_has_sixty_entries(): void
    {
        $this->assertCount(60, $this->resellerMap());
    }

    public function test_bot_reseller_secret_rotate_mapped(): void
    {
        $map = $this->resellerMap();
        $this->assertSame('services.manage', $map['bot_reseller_secret_rotate'] ?? null);
    }

    public function test_reseller_map_values_are_known_permissions(): void
    {
        $allowed = ['users.manage', 'plans.manage', 'broadcast.send', 'receipts.review', 'services.manage', 'users.bulk'];
        foreach ($this->resellerMap() as $op => $perm) {
            $this->assertContains($perm, $allowed, "Unexpected perm for {$op}");
        }
    }

    public function test_admin_only_ops_return_null_permission(): void
    {
        $policy = new MutatePolicyService;
        $this->assertNull($policy->requiredResellerPermission('settings_tab'));
        $this->assertTrue($policy->isAdminOnly('user_merge'));
    }
}
