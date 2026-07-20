<?php

namespace App\Modules\Backup\Mutations;

use App\Modules\Backup\Jobs\ManualBackupJob;
use App\Modules\Backup\Services\BackupExportService;
use App\Modules\Backup\Services\BackupRestoreService;
use App\Modules\Backup\Services\BackupStatusService;
use Illuminate\Contracts\Auth\Authenticatable;

class BackupMutations
{
    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'backup_run' => [self::class, 'run'],
            'backup_reset_stuck' => [self::class, 'resetStuck'],
            'backup_restore' => [self::class, 'restore'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function run(array $payload, ?Authenticatable $actor): array
    {
        $status = app(BackupStatusService::class);
        $status->resetStale();
        if ($status->isRunning()) {
            return ['ok' => false, 'code' => 'already_running', 'status' => 'running', 'message' => 'already_running'];
        }

        $status->startManual();
        ManualBackupJob::dispatch();

        return ['ok' => true, 'async' => true, 'status' => 'running'];
    }

    /** @param  array<string, mixed>  $payload */
    public function resetStuck(array $payload, ?Authenticatable $actor): array
    {
        return app(BackupStatusService::class)->resetStuck();
    }

    /** @param  array<string, mixed>  $payload */
    public function restore(array $payload, ?Authenticatable $actor): array
    {
        if (empty($payload['confirm'])) {
            return svp_err('confirm_required');
        }
        $filename = basename((string) ($payload['filename'] ?? ''));
        $path = app(BackupExportService::class)->resolvePath($filename);
        if ($path === null) {
            return svp_err('not_found');
        }

        return app(BackupRestoreService::class)->restoreFromZip($path, ! empty($payload['restore_panel_db']));
    }
}
