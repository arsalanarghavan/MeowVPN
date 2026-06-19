<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\Artisan;

class WpImportService
{
    public function __construct(
        protected WpDumpParser $parser,
        protected WpTableImporter $tableImporter,
        protected WpSettingsImporter $settingsImporter,
        protected WpDashboardUserImporter $dashboardUserImporter,
        protected WpResellerPermsImporter $resellerPermsImporter,
        protected WpBackupFilesImporter $backupFilesImporter,
        protected WpImportVerifier $verifier,
    ) {}

    /**
     * @param  array{
     *   prefix?: string,
     *   dry_run?: bool,
     *   force?: bool,
     *   verify_only?: bool,
     *   since?: string|null,
     *   default_password?: string,
     *   backups_from?: string|null
     * }  $options
     * @return array<string, mixed>
     */
    public function run(string $path, array $options = []): array
    {
        $prefix = (string) ($options['prefix'] ?? 'wp_');
        $dryRun = (bool) ($options['dry_run'] ?? false);
        $force = (bool) ($options['force'] ?? false);
        $verifyOnly = (bool) ($options['verify_only'] ?? false);
        $since = isset($options['since']) ? (string) $options['since'] : null;
        $defaultPassword = (string) ($options['default_password'] ?? '');
        $backupsFrom = isset($options['backups_from']) ? (string) $options['backups_from'] : null;

        if (! is_file($path)) {
            return ['ok' => false, 'message' => 'dump_not_found'];
        }

        $data = $this->parser->parseFile($path, $prefix);

        if ($verifyOnly) {
            $verify = $this->verifier->verify($data);

            return [
                'ok' => $verify['ok'],
                'verify_only' => true,
                'tables' => $verify['tables'],
                'table_counts' => $data->tableCounts(),
            ];
        }

        $settingsCount = $this->settingsImporter->import($data->options, $dryRun);
        $tableRes = $this->tableImporter->import($data->tables, $force, $dryRun, $since);
        $dashUsers = 0;
        if ($defaultPassword !== '') {
            $dashUsers = $this->dashboardUserImporter->import(
                $data->wpUsers,
                $data->wpUsermeta,
                $data->tables,
                $defaultPassword,
                $dryRun,
            );
        }
        $permsCount = $this->resellerPermsImporter->import($data->options, $dryRun);
        $backupFiles = $this->backupFilesImporter->import($backupsFrom, $dryRun);

        if (! $dryRun) {
            Artisan::call('svp:rebuild-reseller-closure');
        }

        return [
            'ok' => true,
            'dry_run' => $dryRun,
            'settings_keys' => $settingsCount,
            'tables' => $tableRes,
            'dashboard_users' => $dashUsers,
            'reseller_perms' => $permsCount,
            'backup_files' => $backupFiles,
            'table_counts' => $data->tableCounts(),
        ];
    }
}
