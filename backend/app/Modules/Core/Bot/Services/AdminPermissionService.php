<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Services\DashboardBootBuilder;
use App\Services\Mutations\MutatePolicyService;

class AdminPermissionService
{
    /** @var array<string, string> */
    protected const OP_MAP = [
        'receipt_review' => 'receipts.review',
        'plan_manage' => 'plans.manage',
        'card_manage' => 'plans.manage',
        'users_bulk' => 'users.bulk',
        'broadcast' => 'broadcast.send',
        'reseller_list' => 'users.manage',
        'referral_manage' => 'users.manage',
        'services_manage' => 'services.manage',
    ];

    public function __construct(
        protected DashboardBootBuilder $bootBuilder,
        protected AdminNavService $nav,
        protected MutatePolicyService $mutatePolicy,
    ) {}

    /** @return array<string, bool> */
    public function allowedTabs(SvpUser $user): array
    {
        if ($user->role !== 'reseller') {
            return array_fill_keys($this->nav->allTabKeys(), true);
        }

        $dash = DashboardUser::query()->where('svp_user_id', $user->id)->first();
        if (! $dash) {
            return ['reseller_settings' => true];
        }

        return $this->bootBuilder->resellerAllowedTabsMap($dash);
    }

    public function mayAccessTab(SvpUser $user, string $tabKey): bool
    {
        $tabKey = strtolower(trim($tabKey));
        $allowed = $this->allowedTabs($user);

        return ! empty($allowed[$tabKey]);
    }

    public function mayCallOp(SvpUser $user, string $op): bool
    {
        if ($user->role !== 'reseller') {
            return true;
        }
        $perm = self::OP_MAP[strtolower(trim($op))] ?? null;
        if ($perm === null) {
            return false;
        }

        return $this->hasPermission($user, $perm);
    }

    public function permissionActorId(SvpUser $user): int
    {
        return $user->role === 'reseller' ? (int) $user->id : 0;
    }

    public function hasPermission(SvpUser $user, string $perm): bool
    {
        return $this->hasPermissionById((int) $user->id, $perm);
    }

    public function mayCallMutateOp(SvpUser $user, string $op): bool
    {
        $dash = DashboardUser::query()->where('svp_user_id', $user->id)->first();
        if (! $dash && $user->role !== 'reseller') {
            return true;
        }
        if (! $dash) {
            return false;
        }
        $err = $this->mutatePolicy->assertResellerMayRun($op, $dash);

        return $err === null;
    }

    protected function hasPermissionById(int $svpUserId, string $perm): bool
    {
        $dash = DashboardUser::query()->where('svp_user_id', $svpUserId)->first();
        if (! $dash) {
            return false;
        }
        $perms = is_array($dash->permissions_json) ? $dash->permissions_json : [];

        return ! empty($perms[$perm]);
    }
}
