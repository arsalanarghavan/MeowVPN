<?php

namespace App\Services\LiveMetrics;

use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shared live panel metrics blob for SSE (WP Live_Metrics_Collector parity).
 */
class LiveMetricsCollector
{
    public const BLOB_KEY = 'svp_live_metrics_blob';

    public const LOCK_KEY = 'svp_live_collect_lock';

    public function __construct(
        protected DashboardPanelLive $panelLive,
        protected SettingsStore $settings,
    ) {}

    public function cacheTtl(): int
    {
        return $this->panelLive->cacheTtl();
    }

    public function pollIntervalSeconds(): int
    {
        return $this->cacheTtl();
    }

    /** @return array<int> */
    public function panelIdsToCollect(): array
    {
        if (! Schema::hasTable('svp_panels') || ! svp_modules()->isEnabled('xui_panel')) {
            return [];
        }
        $q = DB::table('svp_panels')->where('active', 1);
        if (Schema::hasColumn('svp_panels', 'sort_order')) {
            $q->orderBy('sort_order')->orderBy('id');
        } else {
            $q->orderBy('id');
        }
        $ids = [];
        foreach ($q->get(['id']) as $pn) {
            $pid = (int) $pn->id;
            if ($pid > 0) {
                $ids[] = $pid;
            }
        }

        return array_values(array_unique($ids));
    }

    public function maybeCollect(bool $force = false): bool
    {
        if (! $force && Cache::get(self::LOCK_KEY)) {
            return false;
        }
        if (! $force) {
            $blob = $this->getCachedBlob();
            if (is_array($blob) && isset($blob['collected_at'])) {
                $age = time() - (int) $blob['collected_at'];
                if ($age < $this->pollIntervalSeconds()) {
                    return false;
                }
            }
        }

        return $this->collectAllPanels();
    }

    public function collectAllPanels(): bool
    {
        Cache::put(self::LOCK_KEY, 1, 45);
        try {
            $panelIds = $this->panelIdsToCollect();
            if ($panelIds !== []) {
                $this->panelLive->ensurePanelsReady($panelIds);
            }
            $snapshots = [];
            foreach ($panelIds as $pid) {
                $snapshots[] = $this->panelLive->snapshotForPanel($pid, true);
            }
            $encoded = json_encode($snapshots);
            $version = is_string($encoded) ? md5($encoded) : '';
            $blob = [
                'collected_at' => time(),
                'snapshots' => $snapshots,
                'version' => $version,
            ];
            Cache::put(self::BLOB_KEY, $blob, $this->cacheTtl());

            return true;
        } finally {
            Cache::forget(self::LOCK_KEY);
        }
    }

    /** @return array<string, mixed>|null */
    public function getCachedBlob(): ?array
    {
        $blob = Cache::get(self::BLOB_KEY);

        return is_array($blob) ? $blob : null;
    }

    /** @return array<string, mixed>|null */
    public function snapshotFromBlob(int $panelId): ?array
    {
        if ($panelId < 1) {
            return null;
        }
        $blob = $this->getCachedBlob();
        if (! is_array($blob) || empty($blob['snapshots']) || ! is_array($blob['snapshots'])) {
            return null;
        }
        foreach ($blob['snapshots'] as $snap) {
            if (! is_array($snap)) {
                continue;
            }
            if ((int) ($snap['panelId'] ?? 0) === $panelId) {
                return $snap;
            }
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function streamPayload(): array
    {
        $this->maybeCollect(false);
        $blob = $this->getCachedBlob();
        $snapshots = [];
        $version = '';
        $collected = 0;
        if (is_array($blob)) {
            $snapshots = isset($blob['snapshots']) && is_array($blob['snapshots']) ? $blob['snapshots'] : [];
            $version = (string) ($blob['version'] ?? '');
            $collected = (int) ($blob['collected_at'] ?? 0);
        }

        return [
            'ok' => true,
            'ts' => time(),
            'collected_at' => $collected,
            'version' => $version,
            'livePanelSnapshots' => $snapshots,
        ];
    }

    public function pushIntervalSeconds(): int
    {
        $secs = (int) $this->settings->get('live_sse_push_seconds', 5);

        return max(3, min(30, $secs > 0 ? $secs : 5));
    }
}
