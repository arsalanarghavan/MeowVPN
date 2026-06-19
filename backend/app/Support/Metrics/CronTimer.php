<?php

namespace App\Support\Metrics;

class CronTimer
{
    public static function run(string $jobName, callable $fn): void
    {
        $start = microtime(true);
        try {
            $fn();
        } finally {
            SvpMetrics::observe('cron_job_duration_seconds', microtime(true) - $start, $jobName);
        }
    }
}
