<?php

namespace App\Console\Commands;

use App\Modules\Backup\Services\BackupExportService;
use App\Services\SettingsStore;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'svp:backup-run';

    protected $description = 'Create zip backup of database tables';

    public function handle(BackupExportService $export, SettingsStore $settings): int
    {
        try {
            $path = $export->buildZip();
            $now = time();
            $settings->set('backup_last_run', [
                'at' => $now,
                'built' => true,
                'filename' => basename($path),
            ]);
            $settings->set('backup_last_built_at', $now);
            $this->info("Backup written: {$path}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
