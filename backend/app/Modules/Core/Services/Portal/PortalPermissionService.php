<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\DashboardUser;
use App\Models\SvpUser;

class PortalPermissionService
{
    /** @var array<string, string> */
    protected static array $opMap = [
        'stats' => 'users.manage',
        'membership_pending_page' => 'users.manage',
        'membership_approved_page' => 'users.manage',
        'membership_rejected_page' => 'users.manage',
        'membership_detail' => 'users.manage',
        'membership_approve' => 'users.manage',
        'membership_reject' => 'users.manage',
        'membership_reopen' => 'users.manage',
        'create_service' => 'services.manage',
        'renew_service' => 'services.manage',
        'add_volume' => 'services.manage',
        'service_transfer' => 'services.manage',
        'receipts_page' => 'receipts.review',
        'discount_list' => 'plans.manage',
        'discount_save' => 'plans.manage',
        'discount_delete' => 'plans.manage',
    ];

    public function resellerActorId(SvpUser $admin): int
    {
        return $admin->role === 'reseller' ? (int) $admin->id : 0;
    }

    public function requiredPermission(string $op): ?string
    {
        $op = strtolower(trim($op));

        return self::$opMap[$op] ?? null;
    }

    public function mayCallOp(SvpUser $admin, string $op): bool
    {
        $rid = $this->resellerActorId($admin);
        if ($rid < 1) {
            return true;
        }
        $perm = $this->requiredPermission($op);
        if ($perm === null) {
            return false;
        }

        return $this->hasPermission($rid, $perm);
    }

    public function isSiteOnlyOp(string $op): bool
    {
        return in_array($op, [
            'bulk_days', 'bulk_gb', 'save_crypto', 'rotate_ipn_path',
            'referral_get', 'referral_save',
        ], true);
    }

    protected function hasPermission(int $resellerSvpUserId, string $perm): bool
    {
        $dash = DashboardUser::query()->where('svp_user_id', $resellerSvpUserId)->first();
        if (! $dash) {
            return false;
        }
        $perms = is_array($dash->permissions_json) ? $dash->permissions_json : [];

        return ! empty($perms[$perm]);
    }
}
