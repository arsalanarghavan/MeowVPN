<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database';
    protected $description = 'Backup the database to storage';

    public function handle()
    {
        $this->info('Starting database backup...');

        try {
            $database = config('database.connections.pgsql.database');
            $username = config('database.connections.pgsql.username');
            $host = config('database.connections.pgsql.host');
            $port = config('database.connections.pgsql.port');
            $password = config('database.connections.pgsql.password');

            $filename = 'backup_' . Carbon::now()->format('Y-m-d_H-i-s') . '.sql';
            $filepath = storage_path('app/backups/' . $filename);

            // Create backups directory if it doesn't exist
            if (!file_exists(storage_path('app/backups'))) {
                mkdir(storage_path('app/backups'), 0755, true);
            }

            // Use pg_dump via Docker if running in container
            $command = sprintf(
                'PGPASSWORD=%s pg_dump -h %s -p %s -U %s -d %s > %s 2>&1',
                escapeshellarg($password),
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($database),
                escapeshellarg($filepath)
            );

            exec($command, $output, $returnCode);

            if ($returnCode === 0 && file_exists($filepath)) {
                // Compress the backup
                $compressedFile = $filepath . '.gz';
                exec("gzip -c {$filepath} > {$compressedFile}");
                unlink($filepath);

                $this->info("Database backup completed: {$compressedFile}");

                // Optional: Upload to S3/MinIO if configured
                if (config('backup.upload_to_s3', false)) {
                    $this->uploadToS3($compressedFile);
                }

                // Cleanup old backups (keep last 30 days)
                $this->cleanupOldBackups();
            } else {
                $this->error('Database backup failed');
                return 1;
            }
        } catch (\Exception $e) {
            $this->error('Backup error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    private function uploadToS3(string $filepath)
    {
        try {
            $filename = basename($filepath);
            Storage::disk('s3')->put("backups/{$filename}", file_get_contents($filepath));
            $this->info("Backup uploaded to S3: backups/{$filename}");
        } catch (\Exception $e) {
            $this->warn("Failed to upload to S3: " . $e->getMessage());
        }
    }

    private function cleanupOldBackups()
    {
        $backupDir = storage_path('app/backups');
        $files = glob($backupDir . '/backup_*.sql.gz');
        $cutoffDate = Carbon::now()->subDays(30);

        foreach ($files as $file) {
            if (filemtime($file) < $cutoffDate->timestamp) {
                unlink($file);
                $this->info("Deleted old backup: " . basename($file));
            }
        }
    }
}

