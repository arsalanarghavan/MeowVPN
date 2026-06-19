<?php

namespace App\Modules\Core\Services\Portal;

use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PortalDashboardStatsService
{
    public function __construct(protected ResellerScopeService $scope) {}

    /** @return array<string, mixed> */
    public function buildPayload(int $dayOffset, int $resellerId = 0): array
    {
        $off = max(0, min(7, $dayOffset));
        $statDate = now()->subDays($off)->toDateString();

        if ($resellerId > 0) {
            $scopeIds = $this->scope->moderatableUserIds($resellerId);
            $panelIds = $this->scope->allowedPanelIdsFor($resellerId);
            $users = $this->userServiceCountsForIds($scopeIds);
            $panelRows = $this->panelXrayCountsScoped($panelIds, $scopeIds);
        } else {
            $users = $this->userServiceCounts();
            $panelRows = $this->panelXrayCounts();
        }

        $maxMap = $this->panelMaxOnlineMap($statDate);
        $lines = [];
        foreach ($panelRows as $pid => $row) {
            $lines[] = [
                'panel_id' => $pid,
                'label' => $row['label'],
                'xray_active' => $row['active'],
                'xray_inactive' => $row['inactive'],
                'max_online_day' => $maxMap[$pid] ?? 0,
            ];
        }

        $payload = [
            'stat_date' => $statDate,
            'day_offset' => $off,
            'users' => $users,
            'panels' => $lines,
            'l2tp_services' => $users['services_l2tp'],
        ];
        $payload['text'] = $this->formatText($payload);

        return $payload;
    }

    /** @param  array<int, int>  $userIds */
    protected function userServiceCountsForIds(array $userIds): array
    {
        $empty = $this->emptyUserCounts();
        if ($userIds === []) {
            return $empty;
        }

        return $this->fillUserCounts($empty, $userIds);
    }

    /** @return array<string, int> */
    protected function userServiceCounts(): array
    {
        return $this->fillUserCounts($this->emptyUserCounts());
    }

    /** @return array<string, int> */
    protected function emptyUserCounts(): array
    {
        return [
            'users_approved' => 0,
            'users_pending' => 0,
            'users_rejected' => 0,
            'users_blocked' => 0,
            'users_total' => 0,
            'users_with_telegram' => 0,
            'users_with_bale' => 0,
            'users_today' => 0,
            'services_total' => 0,
            'services_l2tp' => 0,
        ];
    }

    /**
     * @param  array<string, int>  $out
     * @param  array<int, int>|null  $userIds
     * @return array<string, int>
     */
    protected function fillUserCounts(array $out, ?array $userIds = null): array
    {
        $u = DB::table('svp_users');
        $s = DB::table('svp_services');
        if ($userIds !== null) {
            $u->whereIn('id', $userIds);
        }
        $today = now()->toDateString();
        $out['users_approved'] = (int) (clone $u)->where('status', 'approved')->count();
        $out['users_pending'] = (int) (clone $u)->where('status', 'pending')->count();
        $out['users_rejected'] = (int) (clone $u)->where('status', 'rejected')->count();
        $out['users_blocked'] = (int) (clone $u)->where('status', 'blocked')->count();
        $out['users_total'] = (int) (clone $u)->count();
        $out['users_with_telegram'] = (int) (clone $u)->whereNotNull('tg_user_id')->where('tg_user_id', '!=', 0)->count();
        $out['users_with_bale'] = (int) (clone $u)->whereNotNull('bale_user_id')->where('bale_user_id', '!=', 0)->count();
        $out['users_today'] = (int) (clone $u)->whereDate('created_at', $today)->count();

        $sq = DB::table('svp_services')->whereNull('deleted_at');
        if ($userIds !== null) {
            $sq->whereIn('user_id', $userIds);
        }
        $out['services_total'] = (int) (clone $sq)->count();
        $out['services_l2tp'] = (int) (clone $sq)->where('service_type', 'l2tp')->count();

        return $out;
    }

    /**
     * @return array<int, array{active:int, inactive:int, label:string}>
     */
    protected function panelXrayCounts(): array
    {
        $now = $this->sqlNowExpr();
        $rows = DB::table('svp_services')
            ->selectRaw("panel_id,
                SUM(CASE WHEN (expires_at IS NULL OR expires_at > {$now}) THEN 1 ELSE 0 END) AS active_n,
                SUM(CASE WHEN (expires_at IS NOT NULL AND expires_at <= {$now}) THEN 1 ELSE 0 END) AS inactive_n")
            ->whereNull('deleted_at')
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', '')->orWhere('service_type', 'xray');
            })
            ->groupBy('panel_id')
            ->get();

        return $this->mergePanelLabels($rows);
    }

    /**
     * @param  array<int, int>  $panelIds
     * @param  array<int, int>  $scopeUserIds
     * @return array<int, array{active:int, inactive:int, label:string}>
     */
    protected function panelXrayCountsScoped(array $panelIds, array $scopeUserIds): array
    {
        if ($panelIds === [] || $scopeUserIds === []) {
            return [];
        }
        $now = $this->sqlNowExpr();
        $rows = DB::table('svp_services')
            ->selectRaw("panel_id,
                SUM(CASE WHEN (expires_at IS NULL OR expires_at > {$now}) THEN 1 ELSE 0 END) AS active_n,
                SUM(CASE WHEN (expires_at IS NOT NULL AND expires_at <= {$now}) THEN 1 ELSE 0 END) AS inactive_n")
            ->whereNull('deleted_at')
            ->whereIn('panel_id', $panelIds)
            ->whereIn('user_id', $scopeUserIds)
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', '')->orWhere('service_type', 'xray');
            })
            ->groupBy('panel_id')
            ->get();

        return $this->mergePanelLabels($rows, $panelIds);
    }

    /**
     * @param  iterable<object>  $rows
     * @param  array<int, int>|null  $onlyPanelIds
     * @return array<int, array{active:int, inactive:int, label:string}>
     */
    protected function mergePanelLabels(iterable $rows, ?array $onlyPanelIds = null): array
    {
        $byPanel = [];
        foreach ($rows as $r) {
            $pid = (int) $r->panel_id;
            $byPanel[$pid] = [
                'active' => (int) $r->active_n,
                'inactive' => (int) $r->inactive_n,
                'label' => '#'.$pid,
            ];
        }
        $panels = DB::table('svp_panels')->orderBy('sort_order');
        if ($onlyPanelIds !== null) {
            $panels->whereIn('id', $onlyPanelIds !== [] ? $onlyPanelIds : [-1]);
        }
        foreach ($panels->get() as $pn) {
            $pid = (int) $pn->id;
            if (! isset($byPanel[$pid])) {
                $byPanel[$pid] = ['active' => 0, 'inactive' => 0, 'label' => 'پنل #'.$pid];
            }
            $lb = trim((string) ($pn->label ?? ''));
            if ($lb !== '') {
                $byPanel[$pid]['label'] = $lb;
            }
        }
        ksort($byPanel);

        return $byPanel;
    }

    /** @return array<int, int> */
    protected function panelMaxOnlineMap(string $statDate): array
    {
        if (! Schema::hasTable('svp_panel_online_daily')) {
            return [];
        }
        $out = [];
        foreach (DB::table('svp_panel_online_daily')->where('stat_date', $statDate)->get() as $row) {
            $out[(int) $row->panel_id] = (int) ($row->max_online ?? 0);
        }

        return $out;
    }

    /** @param  array<string, mixed>  $data */
    protected function formatText(array $data): string
    {
        $u = $data['users'];
        $d = (string) $data['stat_date'];
        $lbl = ((int) $data['day_offset']) === 0 ? 'امروز ('.$d.')' : 'روز '.(int) $data['day_offset'].' قبل — '.$d;
        $t = "📊 آمار\n➖➖➖➖➖➖➖➖\n";
        $t .= '📅 '.$lbl."\n\n";
        $t .= '✅ تأییدشده: '.(int) $u['users_approved']."\n";
        $t .= '⏳ در انتظار: '.(int) $u['users_pending']."\n";
        $t .= '❌ رد شده: '.(int) $u['users_rejected']."\n";
        $t .= '🚫 مسدود: '.(int) $u['users_blocked']."\n";
        $t .= '👥 کل ربات: '.(int) $u['users_total']."\n";
        $t .= '📱 با تلگرام: '.(int) $u['users_with_telegram']."\n";
        $t .= '💬 با بله: '.(int) $u['users_with_bale']."\n";
        $t .= '🆕 ثبت امروز: '.(int) $u['users_today']."\n\n";
        $t .= '📡 سرویس‌ها (کل): '.(int) $u['services_total'];
        if ((int) $u['services_l2tp'] > 0) {
            $t .= ' · L2TP: '.(int) $u['services_l2tp'];
        }
        $t .= "\n\n➖ پنل‌ها (Xray فعال / منقضی / حداکثر آنلاین روز)\n";
        foreach ($data['panels'] as $pl) {
            $mx = (int) $pl['max_online_day'] > 0 ? (string) (int) $pl['max_online_day'] : '—';
            $t .= '· '.(string) $pl['label'].': ';
            $t .= (int) $pl['xray_active'].' / '.(int) $pl['xray_inactive'].' / '.$mx."\n";
        }
        if ($data['panels'] === []) {
            $t .= "—\n";
        }

        return $t;
    }

    protected function sqlNowExpr(): string
    {
        return DB::connection()->getDriverName() === 'sqlite'
            ? "datetime('now')"
            : 'UTC_TIMESTAMP()';
    }
}
