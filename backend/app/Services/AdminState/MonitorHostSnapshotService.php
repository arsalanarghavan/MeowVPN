<?php

namespace App\Services\AdminState;

use Illuminate\Support\Facades\Http;

class MonitorHostSnapshotService
{
    /**
     * @param  list<array<string, mixed>>  $hosts
     * @return list<array<string, mixed>>
     */
    public function snapshots(array $hosts, bool $refresh = false): array
    {
        if (! $refresh || $hosts === []) {
            return [];
        }

        $out = [];
        foreach ($hosts as $host) {
            $hostId = (int) ($host['id'] ?? 0);
            if ($hostId < 1) {
                continue;
            }
            $url = trim((string) ($host['metrics_url'] ?? ''));
            if ($url === '') {
                $out[] = [
                    'hostId' => $hostId,
                    'label' => (string) ($host['label'] ?? ''),
                    'ok' => false,
                    'error' => 'missing_metrics_url',
                    'checkedAt' => now()->toIso8601String(),
                ];

                continue;
            }

            $bearer = trim((string) ($host['bearer_token'] ?? ''));
            $request = Http::timeout(8);
            if ($bearer !== '') {
                $request = $request->withToken($bearer);
            }

            try {
                $response = $request->get($url);
                $ok = $response->successful();
                $metrics = null;
                if ($ok) {
                    $body = $response->json();
                    $metrics = is_array($body) ? $body : ['raw' => $response->body()];
                }
                $out[] = [
                    'hostId' => $hostId,
                    'label' => (string) ($host['label'] ?? ''),
                    'ok' => $ok,
                    'error' => $ok ? null : ('http_'.$response->status()),
                    'metrics' => $metrics,
                    'checkedAt' => now()->toIso8601String(),
                ];
            } catch (\Throwable $e) {
                $out[] = [
                    'hostId' => $hostId,
                    'label' => (string) ($host['label'] ?? ''),
                    'ok' => false,
                    'error' => 'request_failed',
                    'checkedAt' => now()->toIso8601String(),
                ];
            }
        }

        return $out;
    }
}
