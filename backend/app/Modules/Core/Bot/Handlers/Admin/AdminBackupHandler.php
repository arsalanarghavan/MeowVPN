<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Backup\Jobs\ManualBackupJob;
use App\Modules\Backup\Services\BackupRestoreService;
use App\Modules\Backup\Services\BackupStatusService;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminBackupHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotStateService $state,
        protected BackupRestoreService $restore,
        protected BackupStatusService $backupStatus,
        protected SettingsStore $settings,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.backup', $user, '💾 Backup');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $last = null;
        if (Schema::hasTable('svp_backups')) {
            $last = DB::table('svp_backups')->orderByDesc('id')->first();
        }
        $body = $this->texts->getForUser('msg.admin.tutorial.backup', $user, 'Backup');
        $interval = (string) $this->settings->get('backup_interval_minutes', '60');
        $retention = (string) $this->settings->get('backup_retention_days', '—');
        $body .= "\nInterval: {$interval} min\nRetention: {$retention} days";
        if ($last) {
            $body .= "\n\nLast: #".(int) $last->id.' '.(string) ($last->created_at ?? '');
        } else {
            $body .= "\n\nNo backups yet";
        }
        $body .= "\n\n".$this->texts->getForUser('msg.admin.backup_restore_hint', $user, 'Send a .zip backup file to restore');
        $body .= "\n".$this->texts->getForUser('btn.admin.backup_run', $user, 'Run backup');
        $body .= "\n".$this->texts->getForUser('btn.admin.backup_reset_stuck', $user, 'Reset stuck backup');
        $body .= "\n".$this->texts->getForUser('btn.admin.edit_backup_interval', $user, 'Edit backup interval');
        $body .= "\n".$this->texts->getForUser('btn.admin.edit_backup_retention', $user, 'Edit retention days');
        $this->state->set($user, 'admin_bak_restore', []);
        $ik = [
            [
                ['text' => '▶ Run', 'callback_data' => 'pnl:bk:run'],
                ['text' => '↩ Reset stuck', 'callback_data' => 'pnl:bk:rs'],
            ],
        ];
        $this->send($ctx, $chatId, $body, ['reply_markup' => ['inline_keyboard' => $ik]]);
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $action = (string) ($parts[2] ?? '');
        if ($action === 'run') {
            $this->triggerBackupRun($ctx, $user, $chatId);

            return;
        }
        if ($action === 'rs') {
            $result = $this->backupStatus->resetStuck();
            $msg = ! empty($result['ok'])
                ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Reset')
                : '⛔ '.(string) ($result['message'] ?? 'failed');
            $this->send($ctx, $chatId, $msg);
        }
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trimmed = trim($text);
        if ($trimmed === $this->texts->getForUser('btn.admin.backup_run', $user, 'Run backup')) {
            $this->triggerBackupRun($ctx, $user, $chatId);

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.backup_reset_stuck', $user, 'Reset stuck backup')) {
            $result = $this->backupStatus->resetStuck();
            $msg = ! empty($result['ok'])
                ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Reset')
                : '⛔ '.(string) ($result['message'] ?? 'failed');
            $this->send($ctx, $chatId, $msg);

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.edit_backup_interval', $user, 'Edit backup interval')) {
            app(AdminSettingsHandler::class)->startTabFieldWizard($ctx, $chatId, $user, 'backup', 'backup_interval_minutes', 'Send interval minutes');

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.edit_backup_retention', $user, 'Edit retention days')) {
            app(AdminSettingsHandler::class)->startTabFieldWizard($ctx, $chatId, $user, 'backup', 'backup_retention_days', 'Send retention days');

            return true;
        }

        return false;
    }

    protected function triggerBackupRun(BotContext $ctx, SvpUser $user, int $chatId): void
    {
        $this->backupStatus->resetStale();
        if ($this->backupStatus->isRunning()) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.backup_running', $user, 'Backup already running'));

            return;
        }
        $this->backupStatus->startManual();
        ManualBackupJob::dispatch();
        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.backup_started', $user, 'Backup started'));
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function handleDocument(BotContext $ctx, SvpUser $user, int $chatId, array $message): bool
    {
        if ($this->state->get($user) !== 'admin_bak_restore') {
            return false;
        }
        $doc = (array) ($message['document'] ?? []);
        $fileId = (string) ($doc['file_id'] ?? '');
        $name = (string) ($doc['file_name'] ?? 'backup.zip');
        if ($fileId === '' || ! str_ends_with(strtolower($name), '.zip')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.backup_zip_required', $user, 'Send a .zip file'));

            return true;
        }
        $dest = storage_path('app/backup/bot-upload-'.(int) $user->id.'-'.time().'.zip');
        if (! $this->runtime->downloadFileToPath($ctx, $fileId, $dest)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.backup_download_fail', $user, 'Download failed'));

            return true;
        }
        $result = $this->restore->restoreFromZip($dest, false);
        @unlink($dest);
        $this->state->clear($user);
        $msg = ! empty($result['ok'])
            ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Restored')
            : '⛔ '.(string) ($result['message'] ?? $result['reason'] ?? 'failed');
        $this->send($ctx, $chatId, $msg);

        return true;
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return $this->texts->getForUser('msg.admin.tutorial.backup', $user, 'Backup');
    }
}
