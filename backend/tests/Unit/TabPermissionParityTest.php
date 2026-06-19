<?php

namespace Tests\Unit;

use App\Models\DashboardUser;
use App\Services\DashboardBootBuilder;
use Tests\TestCase;

/** §10.1 tab → permission parity (v15). */
class TabPermissionParityTest extends TestCase
{
    /** @return array<string, string|null> spec tabKey => required perm or null */
    public static function specTabPermissions(): array
    {
        return [
            'dashboard' => null,
            'users' => 'users.manage',
            'users_bulk' => 'users.bulk',
            'broadcast' => 'broadcast.send',
            'plans' => 'plans.manage',
            'plan_cats' => 'plans.manage',
            'cards' => 'plans.manage',
            'receipts' => 'receipts.review',
            'monitoring' => 'services.manage',
            'resellers' => 'users.manage',
            'referral' => 'users.manage',
            'referral_reports' => 'users.manage',
            'reseller_reports' => 'users.manage',
            'marketing_lifecycle' => 'marketing.lifecycle',
            'reseller_bots' => 'services.manage',
            'bot_ui' => 'services.manage',
            'reseller_settings' => null,
            'discounts' => 'plans.manage',
            'reseller_charge' => 'plans.manage',
        ];
    }

    public function test_reseller_allowed_tabs_match_spec_matrix(): void
    {
        $builder = app(DashboardBootBuilder::class);
        $user = new DashboardUser([
            'role' => 'reseller',
            'permissions_json' => array_fill_keys([
                'users.manage', 'plans.manage', 'broadcast.send', 'receipts.review',
                'services.manage', 'users.bulk', 'marketing.lifecycle',
            ], true),
        ]);

        $map = $builder->resellerAllowedTabsMap($user);

        foreach (self::specTabPermissions() as $tab => $perm) {
            if ($perm === null) {
                $this->assertArrayHasKey($tab, $map, "Tab {$tab} should be allowed without perm");
            } else {
                $this->assertArrayHasKey($tab, $map, "Tab {$tab} missing with all perms granted");
            }
        }
    }

    public function test_admin_only_tabs_absent_for_reseller(): void
    {
        $builder = app(DashboardBootBuilder::class);
        $user = new DashboardUser([
            'role' => 'reseller',
            'permissions_json' => array_fill_keys([
                'users.manage', 'plans.manage', 'broadcast.send', 'receipts.review',
                'services.manage', 'users.bulk', 'marketing.lifecycle',
            ], true),
        ]);

        $map = $builder->resellerAllowedTabsMap($user);

        foreach (['bots', 'bot_ui', 'xui_panels'] as $tab) {
            $this->assertArrayNotHasKey($tab, $map, "Tab {$tab} must be admin-only per spec §10.2");
        }
    }

    public function test_resellers_tab_requires_users_manage(): void
    {
        $builder = app(DashboardBootBuilder::class);
        $with = new DashboardUser([
            'role' => 'reseller',
            'permissions_json' => ['users.manage' => true],
        ]);
        $without = new DashboardUser([
            'role' => 'reseller',
            'permissions_json' => ['plans.manage' => true],
        ]);

        $this->assertArrayHasKey('resellers', $builder->resellerAllowedTabsMap($with));
        $this->assertArrayNotHasKey('resellers', $builder->resellerAllowedTabsMap($without));
    }
}
