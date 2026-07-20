<?php

namespace App\Services;

use App\Services\UnitEconomics\CostCalculator;
use App\Services\UnitEconomics\SalesVolumeHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Per-panel financial report (WP SimpleVPBot_Panel_Financial_Reports parity).
 */
class PanelFinancialReportsService
{
    public function __construct(
        protected SalesVolumeHelper $salesVolume,
        protected CostCalculator $costs,
    ) {}

    /**
     * @param  array<string, mixed>  $params  date_from, date_to, calendar (or panel_financial_* aliases)
     * @return array<string, mixed>
     */
    public function build(array $params = []): array
    {
        $from = (string) ($params['date_from'] ?? $params['panel_financial_date_from'] ?? '');
        $to = (string) ($params['date_to'] ?? $params['panel_financial_date_to'] ?? '');
        $period = $this->parseDateRange($from, $to);

        $calendar = preg_replace('/[^a-z]/', '', strtolower((string) ($params['calendar'] ?? $params['panel_financial_calendar'] ?? 'gregorian'))) ?? 'gregorian';
        if (! in_array($calendar, ['jalali', 'gregorian'], true)) {
            $calendar = 'gregorian';
        }

        $txAgg = $this->aggregateTransactionsByPanel($period['from_utc'], $period['to_utc']);
        $rcptAgg = $this->aggregateReceiptsByPanel($period['from_utc'], $period['to_utc']);
        $siteGb = max(0.0, (float) ($txAgg['total_gb'] ?? 0));
        $rangeDays = (int) $period['days'];
        $costCtx = $this->costContext();

        $rows = [];
        foreach ($this->activePanels() as $pn) {
            $pid = (int) ($pn->id ?? 0);
            if ($pid < 1) {
                continue;
            }
            $rows[] = $this->rowForPanel(
                $pid,
                (string) ($pn->label ?? ''),
                $txAgg,
                $rcptAgg,
                $siteGb,
                $rangeDays,
                $costCtx
            );
        }

        $unresolved = null;
        if ($this->hasPanelActivity(0, $txAgg, $rcptAgg)) {
            $unresolved = $this->rowForPanel(
                0,
                'نامشخص / مشترک',
                $txAgg,
                $rcptAgg,
                $siteGb,
                $rangeDays,
                $costCtx
            );
        }

        return [
            'period' => [
                'from' => (string) $period['from'],
                'to' => (string) $period['to'],
                'days' => $rangeDays,
                'calendar' => $calendar,
            ],
            'summary' => $this->summarizeRows($rows, $unresolved),
            'rows' => $rows,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * @return array{from: string, to: string, from_utc: string, to_utc: string, days: int}
     */
    public function parseDateRange(string $from, string $to): array
    {
        $tz = config('app.timezone') ?: 'UTC';
        $from = $this->sanitizeDateYmd($from);
        $to = $this->sanitizeDateYmd($to);

        try {
            if ($from === '' || $to === '') {
                $now = Carbon::now($tz);
                $startLocal = $now->copy()->startOfMonth()->startOfDay();
                $endLocal = $now->copy()->endOfDay();
                $from = $startLocal->toDateString();
                $to = $endLocal->toDateString();
            } else {
                if (strcmp($from, $to) > 0) {
                    [$from, $to] = [$to, $from];
                }
                $startLocal = Carbon::parse($from.' 00:00:00', $tz);
                $endLocal = Carbon::parse($to.' 23:59:59', $tz);
            }
        } catch (\Throwable) {
            $now = Carbon::now($tz);
            $startLocal = $now->copy()->startOfMonth()->startOfDay();
            $endLocal = $now->copy()->endOfDay();
            $from = $startLocal->toDateString();
            $to = $endLocal->toDateString();
        }

        $days = max(1, (int) $startLocal->diffInDays($endLocal) + 1);

        return [
            'from' => $from,
            'to' => $to,
            'from_utc' => $startLocal->copy()->utc()->format('Y-m-d H:i:s'),
            'to_utc' => $endLocal->copy()->utc()->format('Y-m-d H:i:s'),
            'days' => $days,
        ];
    }

    /**
     * @return array{by_panel: array<int, array{sales_gb: float, sales_toman: float, sales_count: int}>, total_gb: float}
     */
    public function aggregateTransactionsByPanel(string $fromUtc, string $toUtc): array
    {
        $byPanel = [];
        $totalGb = 0.0;
        if (! Schema::hasTable('svp_transactions')) {
            return ['by_panel' => $byPanel, 'total_gb' => 0.0];
        }

        $rows = DB::table('svp_transactions')
            ->where('status', 'approved')
            ->whereIn('type', ['purchase', 'renew'])
            ->where('created_at', '>=', $fromUtc)
            ->where('created_at', '<=', $toUtc)
            ->orderBy('id')
            ->get(['id', 'service_id', 'type', 'status', 'meta_json', 'amount', 'created_at']);

        $plans = [];
        $services = [];
        foreach ($rows as $tx) {
            $pid = $this->salesVolume->panelIdFromTransactionRow($tx, $plans, $services);
            $gb = $this->salesVolume->gbFromTransactionRow($tx, $plans, $services);
            $byPanel[$pid] ??= ['sales_gb' => 0.0, 'sales_toman' => 0.0, 'sales_count' => 0];
            $byPanel[$pid]['sales_gb'] += max(0.0, $gb);
            $byPanel[$pid]['sales_toman'] += abs((float) ($tx->amount ?? 0));
            $byPanel[$pid]['sales_count']++;
            $totalGb += max(0.0, $gb);
        }

        foreach ($byPanel as $pid => $vals) {
            $byPanel[$pid]['sales_gb'] = round((float) $vals['sales_gb'], 4);
            $byPanel[$pid]['sales_toman'] = round((float) $vals['sales_toman'], 4);
        }

        return ['by_panel' => $byPanel, 'total_gb' => round($totalGb, 4)];
    }

    /**
     * @return array{by_panel: array<int, float>, total: float}
     */
    public function aggregateReceiptsByPanel(string $fromUtc, string $toUtc): array
    {
        $byPanel = [];
        $total = 0.0;
        if (! Schema::hasTable('svp_receipts') || ! Schema::hasTable('svp_transactions')) {
            return ['by_panel' => $byPanel, 'total' => 0.0];
        }

        $rows = DB::table('svp_receipts as r')
            ->join('svp_transactions as tx', 'tx.id', '=', 'r.transaction_id')
            ->where('r.status', 'approved')
            ->where('tx.status', 'approved')
            ->where('r.created_at', '>=', $fromUtc)
            ->where('r.created_at', '<=', $toUtc)
            ->get(['r.amount', 'tx.service_id', 'tx.meta_json']);

        $plans = [];
        $services = [];
        foreach ($rows as $row) {
            $amt = (float) ($row->amount ?? 0);
            $fake = (object) [
                'service_id' => (int) ($row->service_id ?? 0),
                'meta_json' => $row->meta_json ?? '',
            ];
            $pid = $this->salesVolume->panelIdFromTransactionRow($fake, $plans, $services);
            $byPanel[$pid] = ($byPanel[$pid] ?? 0.0) + $amt;
            $total += $amt;
        }

        foreach ($byPanel as $pid => $amt) {
            $byPanel[$pid] = round((float) $amt, 4);
        }

        return ['by_panel' => $byPanel, 'total' => round($total, 4)];
    }

    /**
     * @param  array<int, array<string, mixed>>  $panelLines
     * @param  array<int, array<string, mixed>>  $sharedLines
     */
    public function costForPanelInRange(
        int $panelId,
        array $panelLines,
        array $sharedLines,
        float $panelGb,
        float $siteGb,
        int $rangeDays,
    ): float {
        $panelGb = max(0.0, $panelGb);
        $siteGb = max(0.0, $siteGb);
        $rangeDays = max(1, $rangeDays);

        $panelCosts = $this->costs->costTotalsFromLines($panelLines);
        $sharedCost = $this->costs->costTotalsFromLines($sharedLines);
        $sharedAlloc = $this->costs->sharedFixedAllocForPanel(
            (float) $sharedCost['fixed_monthly'],
            $panelGb,
            $siteGb
        );

        $monthlyFixed = (float) $panelCosts['fixed_monthly'] + (float) $sharedAlloc;
        $prorate = $rangeDays / (float) CostCalculator::DAYS_PER_MONTH;
        $fixedPeriod = $monthlyFixed * $prorate;
        $variableRate = (float) $panelCosts['variable_per_gb'] + (float) $sharedCost['variable_per_gb'];
        $variablePeriod = $variableRate * $panelGb;

        return round($fixedPeriod + $variablePeriod, 4);
    }

    /** @return array{split: array{shared: array, by_panel: array}, shared_lines: array<int, array<string, mixed>>} */
    protected function costContext(): array
    {
        $activeLines = [];
        if (Schema::hasTable('svp_panel_economics_lines')) {
            $q = DB::table('svp_panel_economics_lines')->where('active', 1);
            if (Schema::hasColumn('svp_panel_economics_lines', 'sort_order')) {
                $q->orderBy('panel_id')->orderBy('sort_order')->orderBy('id');
            } else {
                $q->orderBy('panel_id')->orderBy('id');
            }
            foreach ($q->get() as $row) {
                $activeLines[] = (array) $row;
            }
        }
        $split = $this->costs->splitLinesByScope($activeLines);

        return [
            'split' => $split,
            'shared_lines' => is_array($split['shared'] ?? null) ? $split['shared'] : [],
        ];
    }

    /** @return \Illuminate\Support\Collection<int, object> */
    protected function activePanels()
    {
        if (! Schema::hasTable('svp_panels')) {
            return collect();
        }
        $q = DB::table('svp_panels')->where('active', 1);
        if (Schema::hasColumn('svp_panels', 'sort_order')) {
            $q->orderBy('sort_order')->orderBy('id');
        } else {
            $q->orderBy('id');
        }

        return $q->get();
    }

    /**
     * @param  array{by_panel: array}  $txAgg
     * @param  array{by_panel: array}  $rcptAgg
     */
    protected function hasPanelActivity(int $panelId, array $txAgg, array $rcptAgg): bool
    {
        $tx = $txAgg['by_panel'][$panelId] ?? null;
        if (is_array($tx) && ((float) ($tx['sales_toman'] ?? 0) > 0 || (float) ($tx['sales_gb'] ?? 0) > 0)) {
            return true;
        }

        return ((float) ($rcptAgg['by_panel'][$panelId] ?? 0)) > 0;
    }

    /**
     * @param  array{by_panel: array}  $txAgg
     * @param  array{by_panel: array}  $rcptAgg
     * @param  array{split: array, shared_lines: array}  $costCtx
     * @return array<string, mixed>
     */
    protected function rowForPanel(
        int $panelId,
        string $label,
        array $txAgg,
        array $rcptAgg,
        float $siteGb,
        int $rangeDays,
        array $costCtx,
    ): array {
        $txRow = is_array($txAgg['by_panel'][$panelId] ?? null)
            ? $txAgg['by_panel'][$panelId]
            : ['sales_gb' => 0.0, 'sales_toman' => 0.0, 'sales_count' => 0];
        $salesGb = (float) ($txRow['sales_gb'] ?? 0);
        $salesToman = (float) ($txRow['sales_toman'] ?? 0);
        $salesCount = (int) ($txRow['sales_count'] ?? 0);
        $receipts = (float) ($rcptAgg['by_panel'][$panelId] ?? 0);

        $panelLines = [];
        if ($panelId > 0 && isset($costCtx['split']['by_panel'][$panelId])) {
            $panelLines = (array) $costCtx['split']['by_panel'][$panelId];
        }
        $cost = $this->costForPanelInRange(
            $panelId,
            $panelLines,
            (array) ($costCtx['shared_lines'] ?? []),
            $salesGb,
            $siteGb,
            $rangeDays
        );
        $profit = round($salesToman - $cost, 4);
        $margin = $salesToman > 0 ? round(($profit / $salesToman) * 100, 2) : null;

        return [
            'panel_id' => $panelId,
            'label' => $label,
            'sales_gb' => round($salesGb, 4),
            'sales_toman' => round($salesToman, 4),
            'sales_count' => $salesCount,
            'receipts_toman' => round($receipts, 4),
            'cost_toman' => round($cost, 4),
            'profit_toman' => $profit,
            'margin_pct' => $margin,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>|null  $unresolved
     * @return array<string, mixed>
     */
    protected function summarizeRows(array $rows, ?array $unresolved): array
    {
        $keys = ['sales_gb', 'sales_toman', 'sales_count', 'receipts_toman', 'cost_toman', 'profit_toman'];
        $sum = array_fill_keys($keys, 0.0);
        $all = $rows;
        if (is_array($unresolved)) {
            $all[] = $unresolved;
        }
        foreach ($all as $row) {
            if (! is_array($row)) {
                continue;
            }
            foreach ($keys as $k) {
                if ($k === 'sales_count') {
                    $sum[$k] += (int) ($row[$k] ?? 0);
                } else {
                    $sum[$k] += (float) ($row[$k] ?? 0);
                }
            }
        }
        $sales = (float) $sum['sales_toman'];
        $profit = (float) $sum['profit_toman'];

        return [
            'sales_gb' => round((float) $sum['sales_gb'], 4),
            'sales_toman' => round($sales, 4),
            'sales_count' => (int) $sum['sales_count'],
            'receipts_toman' => round((float) $sum['receipts_toman'], 4),
            'cost_toman' => round((float) $sum['cost_toman'], 4),
            'profit_toman' => round($profit, 4),
            'margin_pct' => $sales > 0 ? round(($profit / $sales) * 100, 2) : null,
        ];
    }

    protected function sanitizeDateYmd(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $raw, $m)) {
            return (string) $m[1];
        }

        return '';
    }
}
