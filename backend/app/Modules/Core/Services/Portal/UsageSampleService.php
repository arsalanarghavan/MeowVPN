<?php

namespace App\Modules\Core\Services\Portal;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsageSampleService
{
    /** @var array<int, string> */
    public const ALLOWED_RANGES = ['1h', '12h', '24h', '7d', '30d', '90d'];

    public function record(int $serviceId, int $usedBytes): void
    {
        if ($serviceId < 1 || ! Schema::hasTable('svp_service_usage_samples')) {
            return;
        }
        DB::table('svp_service_usage_samples')->insert([
            'service_id' => $serviceId,
            'sampled_at' => now(),
            'used_bytes' => max(0, $usedBytes),
        ]);
    }

    public function pruneOlderThanDays(int $days = 120): int
    {
        if (! Schema::hasTable('svp_service_usage_samples')) {
            return 0;
        }
        $cutoff = now()->subDays(max(7, $days));

        return DB::table('svp_service_usage_samples')->where('sampled_at', '<', $cutoff)->delete();
    }

    /**
     * @return array{points:array<int,array{t:string,value:int,label:string}>,total_in_range:int}
     */
    public function chartForRange(int $serviceId, string $rangeKey): array
    {
        $rangeKey = in_array($rangeKey, self::ALLOWED_RANGES, true) ? $rangeKey : '7d';
        if ($serviceId < 1 || ! Schema::hasTable('svp_service_usage_samples')) {
            return ['points' => [], 'total_in_range' => 0];
        }

        $cfg = $this->rangeConfig($rangeKey);
        $end = Carbon::now();
        $start = $end->copy()->subSeconds((int) $cfg['seconds']);
        $samples = DB::table('svp_service_usage_samples')
            ->where('service_id', $serviceId)
            ->whereBetween('sampled_at', [$start, $end])
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'used_bytes']);

        $baseline = DB::table('svp_service_usage_samples')
            ->where('service_id', $serviceId)
            ->where('sampled_at', '<=', $start)
            ->orderByDesc('sampled_at')
            ->value('used_bytes');

        $prevCum = $baseline !== null ? (int) $baseline : 0;
        $points = [];
        $totalInRange = 0;
        $bucketSecs = (int) $cfg['bucket_seconds'];
        $bucketCount = (int) $cfg['bucket_count'];

        for ($i = 0; $i < $bucketCount; $i++) {
            $bucketStart = $start->copy()->addSeconds($i * $bucketSecs);
            $bucketEnd = $start->copy()->addSeconds(($i + 1) * $bucketSecs);
            if ($bucketEnd->gt($end)) {
                $bucketEnd = $end->copy();
            }
            $lastInBucket = null;
            foreach ($samples as $s) {
                $ts = Carbon::parse((string) $s->sampled_at);
                if ($ts->gte($bucketStart) && $ts->lt($bucketEnd)) {
                    $lastInBucket = (int) $s->used_bytes;
                }
            }
            $cum = $lastInBucket ?? $prevCum;
            $delta = max(0, $cum - $prevCum);
            if ($lastInBucket !== null) {
                $prevCum = $cum;
            }
            $points[] = [
                't' => $bucketStart->toIso8601String(),
                'value' => $delta,
                'label' => $this->bucketLabel($bucketStart, $rangeKey),
            ];
            $totalInRange += $delta;
        }

        return ['points' => $points, 'total_in_range' => $totalInRange];
    }

    /** @return array{seconds:int,bucket_seconds:int,bucket_count:int} */
    protected function rangeConfig(string $rangeKey): array
    {
        return match ($rangeKey) {
            '1h' => ['seconds' => 3600, 'bucket_seconds' => 600, 'bucket_count' => 6],
            '12h' => ['seconds' => 43200, 'bucket_seconds' => 3600, 'bucket_count' => 12],
            '24h' => ['seconds' => 86400, 'bucket_seconds' => 3600, 'bucket_count' => 24],
            '30d' => ['seconds' => 2592000, 'bucket_seconds' => 86400, 'bucket_count' => 30],
            '90d' => ['seconds' => 7776000, 'bucket_seconds' => 86400, 'bucket_count' => 90],
            default => ['seconds' => 604800, 'bucket_seconds' => 86400, 'bucket_count' => 7],
        };
    }

    protected function bucketLabel(Carbon $dt, string $rangeKey): string
    {
        if (in_array($rangeKey, ['1h', '12h', '24h'], true)) {
            return $dt->format('H:i');
        }

        return $dt->format('M j');
    }
}
