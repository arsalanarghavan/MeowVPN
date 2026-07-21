<?php

namespace App\Modules\Reseller\Services;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP SimpleVPBot_Reseller_Permission_Gate::permission_actor_id parity.
 */
class ResellerPermissionGateService
{
    /**
     * Reseller owner id for permission scope (0 = site admin actor).
     */
    public function permissionActorId(SvpUser $user): int
    {
        if ($user->role === 'reseller') {
            return (int) $user->id;
        }

        $dash = DashboardUser::query()->where('svp_user_id', $user->id)->first();
        if ($dash) {
            $perms = is_array($dash->permissions_json) ? $dash->permissions_json : [];
            if ($perms !== [] && $this->hasAnyEnabledPermission($perms)) {
                return (int) $user->id;
            }
        }

        return $this->delegatedResellerIdFromBotAdmins($user);
    }

    /** @param  array<string, mixed>  $perms */
    protected function hasAnyEnabledPermission(array $perms): bool
    {
        foreach ($perms as $enabled) {
            if ($enabled) {
                return true;
            }
        }

        return false;
    }

    protected function delegatedResellerIdFromBotAdmins(SvpUser $user): int
    {
        $tg = (int) ($user->tg_user_id ?? 0);
        $bl = (int) ($user->bale_user_id ?? 0);
        if (($tg < 1 && $bl < 1) || ! Schema::hasTable('svp_reseller_bot_profiles')) {
            return 0;
        }

        foreach (DB::table('svp_reseller_bot_profiles')->get() as $prof) {
            $tgIds = $this->decodeAdminIds($prof->admin_telegram_ids ?? null);
            $blIds = $this->decodeAdminIds($prof->admin_bale_ids ?? null);
            if (($tg > 0 && in_array($tg, $tgIds, true)) || ($bl > 0 && in_array($bl, $blIds, true))) {
                return (int) ($prof->reseller_svp_user_id ?? 0);
            }
        }

        return 0;
    }

    /** @return list<int> */
    protected function decodeAdminIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw), fn (int $v) => $v > 0));
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded)
                ? array_values(array_filter(array_map('intval', $decoded), fn (int $v) => $v > 0))
                : [];
        }

        return [];
    }
}
