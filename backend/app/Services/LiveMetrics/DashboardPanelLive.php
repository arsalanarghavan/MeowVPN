<?php

namespace App\Services\LiveMetrics;

use App\Modules\XuiPanel\Services\XuiClient;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Live 3x-ui panel snapshot (WP Dashboard_Panel_Live parity).
 */
class DashboardPanelLive
{
    public const CACHE_PREFIX = 'svp_dash_live_p_';

    public function __construct(
        protected XuiClient $xui,
        protected SettingsStore $settings,
    ) {}

    public function cacheTtl(): int
    {
        $ttl = (int) $this->settings->get('live_metrics_poll_seconds', 15);

        return max(10, min(120, $ttl > 0 ? $ttl : 15));
    }

    public function clearCache(int $panelId): void
    {
        Cache::forget(self::CACHE_PREFIX.$panelId);
    }

    /**
     * @param  array<int>  $panelIds
     */
    public function ensurePanelsReady(array $panelIds): void
    {
        foreach ($panelIds as $pid) {
            $pid = (int) $pid;
            if ($pid < 1) {
                continue;
            }
            $this->xui->runWithPanel($pid, function () {
                $this->xui->loginWithRetries(2, 100000);
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotForPanel(int $panelId, bool $forceRefresh = false): array
    {
        if ($panelId < 1) {
            return [
                'panelId' => 0,
                'ok' => false,
                'error' => 'invalid_panel',
                'onlineNow' => null,
                'status' => null,
                'checkedAt' => gmdate('c'),
            ];
        }

        if (! $forceRefresh) {
            $fromBlob = app(LiveMetricsCollector::class)->snapshotFromBlob($panelId);
            if (is_array($fromBlob)) {
                return $fromBlob;
            }
            $cached = Cache::get(self::CACHE_PREFIX.$panelId);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $out = [
            'panelId' => $panelId,
            'ok' => false,
            'error' => '',
            'onlineNow' => null,
            'status' => null,
            'checkedAt' => gmdate('c'),
        ];

        if (! svp_modules()->isEnabled('xui_panel')) {
            $out['error'] = 'missing_xui';
            Cache::put(self::CACHE_PREFIX.$panelId, $out, 30);

            return $out;
        }

        $row = $this->xui->runWithPanel($panelId, function () use ($panelId) {
            $o = [
                'panelId' => $panelId,
                'ok' => false,
                'error' => '',
                'onlineNow' => null,
                'status' => null,
                'checkedAt' => gmdate('c'),
            ];
            if (! $this->xui->loginWithRetries(3, 200000)) {
                $o['error'] = 'auth_failed';

                return $o;
            }
            $onFetch = $this->xui->fetchOnlines();
            if (empty($onFetch['ok'])) {
                $o['error'] = (string) ($onFetch['error'] ?? 'onlines_failed');
                $o['onlineNow'] = null;
            } else {
                $o['onlineNow'] = $this->xui->countOnlinesResponse($onFetch['json'] ?? null);
            }
            $st = $this->xui->serverStatus();
            $o['status'] = $this->summarizeServerStatus($st);
            $o['ok'] = true;

            return $o;
        });

        if (is_array($row)) {
            $out = $row;
        }
        $out['panelId'] = $panelId;
        $out['checkedAt'] = gmdate('c');

        if (! empty($out['ok']) && isset($out['onlineNow']) && Schema::hasTable('svp_panel_online_daily')) {
            $this->upsertMaxOnline($panelId, (int) $out['onlineNow']);
        }

        Cache::put(self::CACHE_PREFIX.$panelId, $out, $this->cacheTtl());

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function summarizeServerStatus(mixed $json): array
    {
        $out = [];
        if (! is_array($json)) {
            return $out;
        }
        $node = isset($json['obj']) && is_array($json['obj']) ? $json['obj'] : $json;
        $this->walkStatus($node, '', $out, 0);

        return $out;
    }

    /**
     * @param  array<string, mixed>  $out
     */
    protected function walkStatus(mixed $node, string $path, array &$out, int $depth): void
    {
        if ($depth > 4 || count($out) >= 20 || ! is_array($node)) {
            return;
        }
        foreach ($node as $k => $v) {
            if (! is_string($k) || $k === '') {
                continue;
            }
            $key = strtolower($k);
            if (in_array($key, ['password', 'token', 'secret', 'cookie', 'privatekey', 'private_key'], true)) {
                continue;
            }
            $here = $path === '' ? $k : $path.'.'.$k;
            if (is_scalar($v) && (is_numeric($v) || (is_string($v) && strlen((string) $v) < 64))) {
                if (is_numeric($v) || preg_match('/cpu|mem|ram|disk|load|uptime|xray|traffic|net|swap|used|total|percent|count/i', $key)) {
                    $out[$here] = is_numeric($v) ? (float) $v + 0 : (string) $v;
                }
            } elseif (is_array($v) && $depth < 4) {
                $this->walkStatus($v, $here, $out, $depth + 1);
            }
        }
    }

    protected function upsertMaxOnline(int $panelId, int $onlineNow): void
    {
        $statDate = now()->toDateString();
        $existing = DB::table('svp_panel_online_daily')
            ->where('panel_id', $panelId)
            ->where('stat_date', $statDate)
            ->first();
        if ($existing) {
            DB::table('svp_panel_online_daily')
                ->where('id', $existing->id)
                ->update([
                    'max_online' => max((int) $existing->max_online, $onlineNow),
                    'updated_at' => now(),
                ]);
        } else {
            DB::table('svp_panel_online_daily')->insert([
                'panel_id' => $panelId,
                'stat_date' => $statDate,
                'max_online' => $onlineNow,
                'updated_at' => now(),
            ]);
        }
    }
}
