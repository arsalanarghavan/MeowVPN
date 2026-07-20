<?php

namespace App\Services\AdminState;

use App\Models\DashboardUser;
use Illuminate\Http\Request;

class AdminStateContext
{
    /** @var array<string, array{page: int, per_page: int, offset: int}> */
    public array $pagination = [];

    public function __construct(
        public DashboardUser $actor,
        public string $activeTab,
        public int $resellerContextId,
        public bool $includePlansForUserDetail,
        public bool $refreshPanelHealth,
        public bool $refreshLivePanelMetrics,
        public int $overviewMetricsWindowDays,
        public int $statsDay,
        public bool $isReseller,
        public bool $isAdmin,
        public int $actorSvpUserId,
        public Request $request,
        /** @var array<int, int> */
        public array $moderatableUserIds = [],
        /** @var array<int, int> */
        public array $allowedPanelIds = [],
    ) {}

    public static function fromRequest(Request $request, DashboardUser $actor): self
    {
        $rawTab = (string) ($request->query('activeTab', '') ?: $request->query('tab', ''));
        $activeTab = (string) preg_replace('/[^a-z0-9_]/', '', $rawTab);

        $pagination = [];
        foreach (ListPagination::listDefinitions() as $prefix => $def) {
            $pagination[$prefix] = ListPagination::fromRequest(
                $request,
                $prefix,
                $def['default'],
                $def['max']
            );
        }

        $ctx = new self(
            actor: $actor,
            activeTab: $activeTab,
            resellerContextId: max(0, (int) $request->query('resellerContextId', 0)),
            includePlansForUserDetail: (string) $request->query('includePlansForUserDetail') === '1',
            refreshPanelHealth: (string) $request->query('refreshPanelHealth') === '1',
            refreshLivePanelMetrics: (string) $request->query('refreshLivePanelMetrics') === '1',
            overviewMetricsWindowDays: max(1, min(365, (int) $request->query('overview_metrics_window_days', 30))),
            statsDay: (int) $request->query('stats_day', 0),
            isReseller: $actor->role === 'reseller',
            isAdmin: $actor->role === 'admin',
            actorSvpUserId: (int) ($actor->svp_user_id ?? 0),
            request: $request,
        );
        $ctx->pagination = $pagination;

        return $ctx;
    }

    public function page(string $prefix): array
    {
        return $this->pagination[$prefix] ?? ['page' => 1, 'per_page' => 20, 'offset' => 0];
    }

    public function needsPanelHealth(): bool
    {
        return in_array($this->activeTab, ['dashboard', 'monitoring', 'xui_panels', 'vpn_server', 'xray_core'], true)
            || $this->refreshPanelHealth;
    }

    public function needsLiveMetrics(): bool
    {
        return $this->activeTab === 'monitoring' || $this->refreshLivePanelMetrics;
    }

    public function needsCatalog(): bool
    {
        if ($this->includePlansForUserDetail) {
            return true;
        }

        return in_array($this->activeTab, [
            'plans', 'plan_cats', 'cards', 'monitoring', 'xui_panels', 'reseller_panels',
            'resellers', 'reseller_xui_panels', 'dashboard', 'discounts', 'l2tp_servers',
            'xray_core', 'xray_inbounds', 'xray_hosts', 'vpn_server', 'tunnel_nodes',
            'users', 'users_bulk', 'configs', 'site_settings',
        ], true) || $this->isReseller;
    }

    public function needsUsersList(): bool
    {
        return in_array($this->activeTab, ['users', 'dashboard', 'users_bulk', 'marketing_lifecycle'], true);
    }

    public function needsPendingUsers(): bool
    {
        return in_array($this->activeTab, ['users', 'dashboard'], true);
    }

    public function needsResellersList(): bool
    {
        if ($this->isReseller && $this->activeTab === 'resellers') {
            return true;
        }

        if ($this->isReseller && $this->activeTab === 'reseller_xui_panels') {
            return true;
        }

        return ! $this->isReseller && in_array($this->activeTab, ['resellers', 'reseller_xui_panels', 'dashboard', 'site_settings'], true);
    }

    public function needsReceipts(): bool
    {
        return $this->needsPayments();
    }

    public function needsPayments(): bool
    {
        return in_array($this->activeTab, ['payments', 'receipts', 'dashboard'], true);
    }

    public function needsPanelFinancialReports(): bool
    {
        return $this->activeTab === 'panel_financial_reports';
    }

    public function needsBroadcasts(): bool
    {
        return $this->activeTab === 'broadcast';
    }

    public function needsDiscounts(): bool
    {
        return $this->activeTab === 'discounts';
    }

    public function needsTexts(): bool
    {
        return $this->activeTab === 'texts';
    }

    public function needsBots(): bool
    {
        return in_array($this->activeTab, ['reseller_bots', 'bots', 'reseller_settings'], true);
    }

    public function needsOverview(): bool
    {
        return in_array($this->activeTab, ['dashboard', 'monitoring', 'overview'], true);
    }

    public function needsMonitoring(): bool
    {
        return in_array($this->activeTab, ['dashboard', 'monitoring', 'xui_panels', 'vpn_server', 'xray_core', 'tunnel_nodes'], true);
    }

    public function needsReferral(): bool
    {
        return $this->activeTab === 'referral_reports';
    }

    public function needsResellerReports(): bool
    {
        return $this->activeTab === 'reseller_reports';
    }

    public function needsMarketing(): bool
    {
        return $this->activeTab === 'marketing_lifecycle';
    }

    public function needsUnitEconomics(): bool
    {
        return in_array($this->activeTab, ['unit_economics', 'xui_panels'], true);
    }

    public function needsResellerExtras(): bool
    {
        return $this->isReseller && $this->actorSvpUserId > 0;
    }

    public function l2tpEnabled(): bool
    {
        return (bool) svp_modules()->isEnabled('l2tp');
    }
}
