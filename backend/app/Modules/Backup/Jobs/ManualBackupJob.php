<?php

namespace App\Modules\Backup\Jobs;

use App\Modules\Backup\Services\BackupExportService;
use App\Modules\Backup\Services\BackupStatusService;
use App\Services\SettingsStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ManualBackupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(
        BackupExportService $export,
        BackupStatusService $status,
        SettingsStore $settings,
    ): void {
        try {
            $path = $export->buildZip();
            $now = time();
            $settings->set('backup_last_run', [
                'at' => $now,
                'built' => true,
                'filename' => basename($path),
            ]);
            $settings->set('backup_last_built_at', $now);

            $status->markDone([
                'built' => true,
                'stored_on_site' => true,
                'last_built_at' => $now,
                'sent' => 0,
                'failed' => 0,
                'message' => 'زیپ بکاپ ساخته و روی سایت ذخیره شد.',
            ]);
        } catch (\Throwable $e) {
            $status->markFailed($e->getMessage());
        }
    }
}
