<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\XuiPanel\Services\PanelSessionKeeperService;
use App\Services\LiveMetrics\LiveMetricsCollector;
use App\Services\SettingsStore;
use App\Support\Metrics\SvpMetrics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CronController extends Controller
{
    public function status(Request $request, LiveMetricsCollector $collector, SettingsStore $settings): JsonResponse
    {
        $blob = $collector->getCachedBlob() ?? [];

        return response()->json([
            'ok' => true,
            'last_session_keeper_at' => (int) $settings->get('last_session_keeper_at', 0),
            'internal_secret_configured' => trim((string) $settings->get('internal_cron_secret', '')) !== '',
            'live_metrics_poll_seconds' => $collector->pollIntervalSeconds(),
            'live_sse_push_seconds' => $collector->pushIntervalSeconds(),
            'live_metrics_collected_at' => (int) ($blob['collected_at'] ?? 0),
            'live_metrics_version' => (string) ($blob['version'] ?? ''),
            'cron_job_duration_seconds' => [
                'svp:live_metrics_collect' => SvpMetrics::get('cron_job_duration_seconds:svp:live_metrics_collect'),
                'svp:panel_session_keeper' => SvpMetrics::get('cron_job_duration_seconds:svp:panel_session_keeper'),
            ],
        ]);
    }

    public function sessionKeeper(Request $request, PanelSessionKeeperService $keeper, SettingsStore $settings): JsonResponse
    {
        $expected = trim((string) $settings->get('internal_cron_secret', ''));
        $got = trim((string) $request->header('X-SVP-Internal-Secret', ''));
        if ($expected === '' || $got === '' || ! hash_equals($expected, $got)) {
            return response()->json(['ok' => false, 'message' => 'forbidden'], 403);
        }

        $result = $keeper->run();

        return response()->json($result, ! empty($result['ok']) ? 200 : 400);
    }
}
