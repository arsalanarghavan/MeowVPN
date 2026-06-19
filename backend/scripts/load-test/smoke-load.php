#!/usr/bin/env php
<?php

/**
 * Lightweight load smoke test (no k6 dependency).
 *
 * Usage:
 *   php scripts/load-test/smoke-load.php --base=http://127.0.0.1:8080 --requests=50
 */

declare(strict_types=1);

$opts = getopt('', ['base:', 'requests:', 'webhook-secret:', 'help']);
if (isset($opts['help'])) {
    echo "Options:\n";
    echo "  --base=URL           Base URL (default http://127.0.0.1:8080)\n";
    echo "  --requests=N         Requests per endpoint (default 30)\n";
    echo "  --webhook-secret=S   Telegram webhook secret (optional)\n";
    exit(0);
}

$base = rtrim((string) ($opts['base'] ?? 'http://127.0.0.1:8080'), '/');
$count = max(1, (int) ($opts['requests'] ?? 30));
$secret = (string) ($opts['webhook-secret'] ?? '');

function percentile(array $values, float $p): float
{
    if ($values === []) {
        return 0.0;
    }
    sort($values);
    $idx = (int) ceil(($p / 100) * count($values)) - 1;

    return (float) $values[max(0, $idx)];
}

/** @return array{ok:bool, ms:float, code:int} */
function httpGet(string $url): array
{
    $start = microtime(true);
    $ctx = stream_context_create(['http' => ['timeout' => 15, 'ignore_errors' => true]]);
    $body = @file_get_contents($url, false, $ctx);
    $ms = (microtime(true) - $start) * 1000;
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }

    return ['ok' => $body !== false && $code >= 200 && $code < 300, 'ms' => $ms, 'code' => $code];
}

/** @return array{ok:bool, ms:float, code:int} */
function httpPostJson(string $url, array $payload): array
{
    $start = microtime(true);
    $ctx = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ]);
    $body = @file_get_contents($url, false, $ctx);
    $ms = (microtime(true) - $start) * 1000;
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int) $m[1];
    }

    return ['ok' => $body !== false && $code >= 200 && $code < 300, 'ms' => $ms, 'code' => $code];
}

function runBench(string $label, callable $fn, int $count): void
{
    $latencies = [];
    $errors = 0;
    for ($i = 0; $i < $count; $i++) {
        $r = $fn();
        $latencies[] = $r['ms'];
        if (! $r['ok']) {
            $errors++;
        }
    }
    $errRate = round(100 * $errors / $count, 2);
    echo "{$label}\n";
    echo "  requests: {$count}\n";
    echo "  errors:   {$errors} ({$errRate}%)\n";
    echo '  p50 ms:   '.round(percentile($latencies, 50), 1)."\n";
    echo '  p95 ms:   '.round(percentile($latencies, 95), 1)."\n\n";
}

echo "SVP smoke load — {$base}\n\n";

runBench('GET /health/ready', fn () => httpGet("{$base}/health/ready"), $count);

if ($secret !== '') {
    $payload = ['update_id' => random_int(1, 999999), 'message' => ['from' => ['id' => 1], 'chat' => ['id' => 1], 'text' => 'ping']];
    runBench(
        'POST /api/v1/webhook/telegram/{secret}',
        fn () => httpPostJson("{$base}/api/v1/webhook/telegram/{$secret}", $payload),
        $count
    );
} else {
    echo "Skipping webhook bench (pass --webhook-secret=...)\n\n";
}

echo "Done.\n";
