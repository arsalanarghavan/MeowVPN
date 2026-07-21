<?php

namespace App\Support;

use App\Models\SvpPlan;
use Illuminate\Support\Facades\Schema;

/**
 * Per-plan user-facing quota display (show cap vs hide as unlimited).
 * Port of SimpleVPBot_Plan_Quota_Display + Model_Plan helpers.
 */
class PlanQuotaDisplay
{
    public const MODE_SHOW = 'show';

    public const MODE_HIDE_UNLIMITED = 'hide_as_unlimited';

    public static function normalizeMode(?string $mode): string
    {
        $m = strtolower(trim((string) $mode));

        return $m === self::MODE_HIDE_UNLIMITED || $m === 'hide'
            ? self::MODE_HIDE_UNLIMITED
            : self::MODE_SHOW;
    }

    public static function isHiddenFromUser(object|array|null $plan): bool
    {
        if ($plan === null) {
            return false;
        }
        $mode = is_array($plan)
            ? ($plan['quota_display_mode'] ?? self::MODE_SHOW)
            : ($plan->quota_display_mode ?? self::MODE_SHOW);

        return self::normalizeMode((string) $mode) === self::MODE_HIDE_UNLIMITED;
    }

    public static function resolveModeForService(object|array|null $svc): string
    {
        if ($svc === null) {
            return self::MODE_SHOW;
        }
        $pid = (int) (is_array($svc) ? ($svc['plan_id'] ?? 0) : ($svc->plan_id ?? 0));
        if ($pid < 1) {
            return self::MODE_SHOW;
        }
        if (! Schema::hasTable('svp_plans') || ! Schema::hasColumn('svp_plans', 'quota_display_mode')) {
            return self::MODE_SHOW;
        }
        $plan = SvpPlan::query()->find($pid);
        if (! $plan) {
            return self::MODE_SHOW;
        }

        return self::normalizeMode((string) ($plan->quota_display_mode ?? self::MODE_SHOW));
    }

    public static function isHiddenForService(object|array|null $svc): bool
    {
        return self::resolveModeForService($svc) === self::MODE_HIDE_UNLIMITED;
    }

    /**
     * Mask total/remaining quota in bot/portal usage stats (usage bytes unchanged).
     *
     * @param  array<string, mixed>  $stats
     * @return array<string, mixed>
     */
    public static function maskUsageStatsForUser(object|array|null $svc, array $stats): array
    {
        if (! self::isHiddenForService($svc)) {
            return $stats;
        }
        $stats['total_quota'] = self::unlimitedLabel();
        $stats['remained_h'] = self::unlimitedLabel();
        $stats['quota_hidden_from_user'] = 1;
        if (! empty($stats['usage_footer_notes'])) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $stats['usage_footer_notes']) ?: [];
            $keep = [];
            foreach ($lines as $line) {
                $line = trim((string) $line);
                if ($line === '') {
                    continue;
                }
                if (mb_strpos($line, 'سقف نمایش') !== false) {
                    continue;
                }
                $keep[] = $line;
            }
            $stats['usage_footer_notes'] = $keep !== [] ? implode("\n", $keep) : '';
        }

        return $stats;
    }

    public static function stripQuotaLinesFromPanelText(string $text): string
    {
        $out = $text;
        $patterns = [
            '/\n[^\n]*سهمیه\s*:[^\n]*/u',
            '/\n[^\n]*حجم کل\s*:[^\n]*/u',
            '/\n[^\n]*باقی[\x{200c}\x{0640}-]*مانده\s*:[^\n]*/u',
            '/\n[^\n]*Quota\s*:[^\n]*/ui',
            '/\n[^\n]*Total quota\s*:[^\n]*/ui',
            '/\n[^\n]*Remaining\s*:[^\n]*/ui',
            '/\n[^\n]*Traffic\s*:[^\n]*/ui',
        ];
        foreach ($patterns as $pattern) {
            $out = (string) preg_replace($pattern, '', $out);
        }
        $out = (string) preg_replace("/\n{3,}/", "\n\n", $out);

        return trim($out);
    }

    public static function unlimitedLabel(): string
    {
        return '♾️ نامحدود';
    }

    /**
     * Volume label for bot buy/confirm (fixed plans; per-GB checkout may pass chosen GB).
     */
    public static function volumeLabelForUser(object|array|null $plan, ?int $gb = null): string
    {
        if (self::isHiddenFromUser($plan)) {
            return self::unlimitedLabel();
        }
        $g = $gb !== null
            ? $gb
            : (int) (is_array($plan) ? ($plan['traffic_gb'] ?? 0) : (is_object($plan) ? ($plan->traffic_gb ?? 0) : 0));
        if ($g < 1) {
            return self::unlimitedLabel();
        }

        return $g.' GB';
    }
}
