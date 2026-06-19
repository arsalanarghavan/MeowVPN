<?php

namespace App\Modules\Reseller\Services;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Services\DashboardBootBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResellerScopeService
{
    public function __construct(
        protected DashboardBootBuilder $bootBuilder,
    ) {}

    /** @return array<int, int> */
    public function downlineUserIds(int $resellerSvpUserId): array
    {
        if ($resellerSvpUserId < 1) {
            return [];
        }

        $ids = [];
        if (Schema::hasTable('svp_reseller_closure')) {
            $ids = DB::table('svp_reseller_closure')
                ->where('ancestor_id', $resellerSvpUserId)
                ->where('descendant_id', '!=', $resellerSvpUserId)
                ->pluck('descendant_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }

        if ($ids === []) {
            return [$resellerSvpUserId];
        }

        if (! in_array($resellerSvpUserId, $ids, true)) {
            $ids[] = $resellerSvpUserId;
        }

        return array_values(array_unique($ids));
    }

    /** @return array<int, int> */
    public function moderatableUserIds(int $resellerSvpUserId): array
    {
        $base = $this->downlineUserIds($resellerSvpUserId);
        if ($resellerSvpUserId < 1 || ! Schema::hasTable('svp_users')) {
            return $base;
        }

        $extra = SvpUser::query()
            ->where('signup_reseller_svp_id', $resellerSvpUserId)
            ->where('id', '!=', $resellerSvpUserId)
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();

        return array_values(array_unique(array_filter(
            array_merge($base, $extra),
            fn ($v) => (int) $v > 0
        )));
    }

    /** @return array<int, int> */
    public function allowedPanelIdsFor(int $resellerSvpUserId): array
    {
        if ($resellerSvpUserId < 1) {
            return [];
        }

        $out = [];
        if (Schema::hasTable('svp_reseller_panel_prices')) {
            $rows = DB::table('svp_reseller_panel_prices')
                ->where('reseller_svp_user_id', $resellerSvpUserId)
                ->where('active', 1)
                ->get(['panel_id']);
            foreach ($rows as $row) {
                $pid = (int) $row->panel_id;
                if ($pid > 0) {
                    $out[] = $pid;
                }
            }
        }

        if (Schema::hasTable('svp_reseller_wholesale_line_assignments')
            && Schema::hasTable('svp_reseller_wholesale_lines')) {
            $panelIds = DB::table('svp_reseller_wholesale_line_assignments as a')
                ->join('svp_reseller_wholesale_lines as l', 'l.id', '=', 'a.line_id')
                ->where('a.reseller_svp_user_id', $resellerSvpUserId)
                ->pluck('l.panel_id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $out = array_merge($out, $panelIds);
        }

        return array_values(array_unique(array_filter($out, fn ($v) => $v > 0)));
    }

    public function resellerMayModerateUser(int $resellerSvpUserId, int $targetUserId): bool
    {
        if ($targetUserId < 1 || $resellerSvpUserId < 1) {
            return false;
        }

        if (in_array($targetUserId, $this->moderatableUserIds($resellerSvpUserId), true)) {
            return true;
        }

        $row = SvpUser::query()->find($targetUserId);
        if (! $row) {
            return false;
        }

        return (int) ($row->invited_by ?? 0) === $resellerSvpUserId
            || (int) ($row->signup_reseller_svp_id ?? 0) === $resellerSvpUserId;
    }

    public function resellerMayRequestAdminTab(DashboardUser $actor, string $activeTab): bool
    {
        if ($actor->role !== 'reseller' || $activeTab === '') {
            return true;
        }

        $allowed = $this->bootBuilder->resellerAllowedTabsMap($actor);

        return isset($allowed[$activeTab]) && $allowed[$activeTab] === true;
    }

    public function validateResellerContextId(int $ownerCtx): ?int
    {
        if ($ownerCtx < 1) {
            return null;
        }

        $user = SvpUser::query()->find($ownerCtx);
        if (! $user || (string) $user->role !== 'reseller') {
            return null;
        }

        return $ownerCtx;
    }
}
