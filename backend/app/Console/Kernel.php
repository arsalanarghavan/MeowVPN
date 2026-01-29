<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Services\ServiceLifecycleService;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Monitor traffic every 10 minutes
        $schedule->job(\App\Jobs\MonitorServices::class)->everyTenMinutes();

        // Sync multi-server traffic every 5 minutes
        $schedule->job(\App\Jobs\SyncMultiServerTraffic::class)->everyFiveMinutes();

        // Check expiration daily
        $schedule->call(function () {
            app(ServiceLifecycleService::class)->expireSubscriptions();
        })->daily();

        // Cleanup expired subscriptions daily
        $schedule->job(\App\Jobs\CleanupExpiredServices::class)->daily();

        // Generate monthly invoices (first day of month at 00:00)
        $schedule->job(\App\Jobs\GenerateMonthlyInvoices::class)->monthlyOn(1, '00:00');

        // Database backup daily at 2 AM
        $schedule->command('backup:database')->dailyAt('02:00');
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
    }
}

