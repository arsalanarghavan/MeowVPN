<?php

namespace App\Services\UnitEconomics;

/**
 * Panel cost line math (WP Unit_Economics_Calculator subset for financial reports).
 */
class CostCalculator
{
    public const HOURS_PER_MONTH = 730;

    public const DAYS_PER_MONTH = 30;

    /**
     * @param  array<int, array<string, mixed>>  $allLines
     * @return array{shared: array<int, array<string, mixed>>, by_panel: array<int, array<int, array<string, mixed>>>}
     */
    public function splitLinesByScope(array $allLines): array
    {
        $shared = [];
        $byPanel = [];
        foreach ($allLines as $line) {
            if (! is_array($line)) {
                continue;
            }
            $pid = (int) ($line['panel_id'] ?? 0);
            if ($pid === 0) {
                $shared[] = $line;

                continue;
            }
            $byPanel[$pid] ??= [];
            $byPanel[$pid][] = $line;
        }

        return ['shared' => $shared, 'by_panel' => $byPanel];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{fixed_monthly: float, variable_per_gb: float}
     */
    public function costTotalsFromLines(array $lines): array
    {
        $agg = $this->aggregateLines($this->sanitizeLines($lines));

        return [
            'fixed_monthly' => (float) $agg['fixed_monthly'],
            'variable_per_gb' => (float) $agg['variable_per_gb'],
        ];
    }

    public function sharedFixedAllocForPanel(float $sharedFixed, float $panelVolume, float $siteVolume): float
    {
        $sharedFixed = max(0.0, $sharedFixed);
        $panelVolume = max(0.0, $panelVolume);
        $siteVolume = max(0.0, $siteVolume);
        if ($sharedFixed <= 0 || $panelVolume <= 0 || $siteVolume <= 0) {
            return 0.0;
        }

        return $sharedFixed * ($panelVolume / $siteVolume);
    }

    public function lineMonthlyFixedCost(float $costAmount, string $billingCycle): float
    {
        $cost = max(0.0, $costAmount);
        $cycle = preg_replace('/[^a-z0-9_]/', '', strtolower($billingCycle)) ?? 'monthly';
        if ($cycle === 'hourly') {
            return $cost * self::HOURS_PER_MONTH;
        }
        if ($cycle === 'daily') {
            return $cost * self::DAYS_PER_MONTH;
        }
        if ($cycle === 'per_gb') {
            return 0.0;
        }

        return $cost;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    public function sanitizeLines(array $lines): array
    {
        $out = [];
        foreach ($lines as $row) {
            if (! is_array($row)) {
                continue;
            }
            if (array_key_exists('active', $row) && empty($row['active'])) {
                continue;
            }
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $cycle = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($row['billing_cycle'] ?? 'monthly'))) ?? 'monthly';
            if (! in_array($cycle, ['hourly', 'daily', 'monthly', 'per_gb'], true)) {
                $cycle = 'monthly';
            }
            $costAmount = (float) ($row['cost_amount'] ?? $row['amount'] ?? 0);
            $out[] = [
                'panel_id' => max(0, (int) ($row['panel_id'] ?? 0)),
                'category' => (string) ($row['category'] ?? 'external_server'),
                'label' => $label,
                'provider' => (string) ($row['provider'] ?? ''),
                'cost_amount' => max(0.0, $costAmount),
                'billing_cycle' => $cycle,
            ];
        }

        return $out;
    }

    /**
     * @param  array<int, array<string, mixed>>  $linesClean
     * @return array{fixed_monthly: float, variable_per_gb: float}
     */
    public function aggregateLines(array $linesClean): array
    {
        $fixedMonthly = 0.0;
        $variablePerGb = 0.0;
        foreach ($linesClean as $line) {
            $cycle = (string) ($line['billing_cycle'] ?? 'monthly');
            $cost = (float) ($line['cost_amount'] ?? 0);
            if ($cycle === 'per_gb') {
                $variablePerGb += $cost;
            } else {
                $fixedMonthly += $this->lineMonthlyFixedCost($cost, $cycle);
            }
        }

        return [
            'fixed_monthly' => $fixedMonthly,
            'variable_per_gb' => $variablePerGb,
        ];
    }
}
