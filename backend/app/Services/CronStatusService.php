<?php

namespace App\Services;

use App\Services\LiveMetrics\LiveMetricsCollector;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\Cache;

class CronStatusService
{
    public function __construct(
        protected SettingsStore $settings,
        protected LiveMetricsCollector $liveMetrics,
    ) {}

    /** @return array<string, mixed> */
    public function forDashboard(): array
    {
        $blob = $this->liveMetrics->getCachedBlob();
        $secret = trim((string) $this->settings->get('internal_cron_secret', ''));

        return [
            'ok' => true,
            'scheduler_mode' => 'laravel',
            'internal_secret_configured' => $secret !== '',
            'session_keeper_rest_url' => url('/api/v1/internal/session-keeper'),
            'session_keeper_crontab_line' => '*/15 * * * * curl -fsS -X POST -H "X-SVP-Internal-Secret: YOUR_SECRET" '.escapeshellarg(url('/api/v1/internal/session-keeper')),
            'last_session_keeper_at' => (int) Cache::get('svp_last_session_keeper_at', 0),
            'live_metrics_poll_seconds' => $this->liveMetrics->pollIntervalSeconds(),
            'live_metrics_collected_at' => is_array($blob) ? (int) ($blob['collected_at'] ?? 0) : 0,
            'server_crontab_line' => '* * * * * cd '.base_path().' && php artisan schedule:run >> /dev/null 2>&1',
        ];
    }
}
