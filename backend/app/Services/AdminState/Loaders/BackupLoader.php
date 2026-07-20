<?php

namespace App\Services\AdminState\Loaders;

use App\Modules\Backup\Services\BackupExportService;
use App\Modules\Backup\Services\BackupStatusService;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\BackupIntervalResolver;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class BackupLoader extends AbstractLoader
{
    public function __construct(
        protected BackupExportService $export,
        protected BackupStatusService $status,
        protected BackupIntervalResolver $intervals,
        protected SettingsStore $settings,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->activeTab === 'backup' && svp_modules()->isEnabled('backup');
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $panels = [];
        if (DB::getSchemaBuilder()->hasTable('svp_panels')) {
            $panels = DB::table('svp_panels')
                ->where('active', true)
                ->orderBy('sort_order')
                ->get(['id', 'label'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'label' => (string) $p->label])
                ->all();
        }

        $lastRun = $this->settings->get('backup_last_run', []);
        if (! is_array($lastRun)) {
            $lastRun = [];
        }

        $result->merge([
            'backupRows' => $this->export->listFiles(),
            'backupPanels' => $panels,
            'backupStatus' => $this->status->getStatus(),
            'backupMeta' => [
                'last_backup_at' => (int) ($lastRun['at'] ?? 0),
                'last_built_at' => (int) $this->settings->get('backup_last_built_at', 0),
                'store_on_site' => (bool) $this->settings->get('backup_store_on_site', true),
                'backup_interval_minutes' => $this->intervals->minutes(),
                'cron_registered' => true,
                'last_run' => $lastRun,
                'backup_display_timezone' => config('app.timezone', 'UTC'),
                'site_timezone' => config('app.timezone', 'UTC'),
            ],
        ]);
    }
}
