<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\LiveMetrics\LiveMetricsCollector;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LiveStreamController extends Controller
{
    public function __invoke(Request $request, LiveMetricsCollector $collector): StreamedResponse
    {
        $pushSecs = $collector->pushIntervalSeconds();
        $maxSecs = max(60, min(1800, (int) config('svp.live_sse_max_seconds', 600)));

        return response()->stream(function () use ($collector, $pushSecs, $maxSecs) {
            @ini_set('zlib.output_compression', '0');
            @ini_set('output_buffering', 'off');
            while (ob_get_level() > 0) {
                ob_end_flush();
            }
            if (function_exists('ignore_user_abort')) {
                ignore_user_abort(true);
            }
            @set_time_limit(0);

            if (app()->runningUnitTests()) {
                $payload = $collector->streamPayload();
                echo "event: metrics\n";
                echo 'data: '.json_encode($payload)."\n\n";

                return;
            }

            $started = time();
            $lastHash = '';

            while ((time() - $started) < $maxSecs) {
                if (connection_aborted()) {
                    break;
                }
                $payload = $collector->streamPayload();
                $hash = md5((string) json_encode($payload));
                if ($hash !== $lastHash) {
                    echo "event: metrics\n";
                    echo 'data: '.json_encode($payload)."\n\n";
                    $lastHash = $hash;
                } else {
                    echo ': keepalive '.time()."\n\n";
                }
                if (function_exists('flush')) {
                    flush();
                }
                if (connection_aborted()) {
                    break;
                }
                sleep($pushSecs);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=UTF-8',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
