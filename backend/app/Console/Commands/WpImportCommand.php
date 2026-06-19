<?php

namespace App\Console\Commands;

use App\Services\Migration\WpImportService;
use Illuminate\Console\Command;

class WpImportCommand extends Command
{
    protected $signature = 'wp:import
        {path : Path to WordPress SQL dump}
        {--prefix=wp_ : Table prefix in dump}
        {--dry-run : Parse and report without writing}
        {--force : Overwrite existing rows by id}
        {--verify-only : Compare dump row counts with database}
        {--since= : Import rows updated/created since UTC datetime}
        {--default-password= : Bcrypt password for imported dashboard operators}
        {--backups-from= : Directory with legacy on-site backup zip files}';

    protected $description = 'Import WordPress SimpleVPBot SQL dump into Laravel database';

    public function handle(WpImportService $import): int
    {
        $path = (string) $this->argument('path');
        $defaultPassword = (string) $this->option('default-password');
        if ($defaultPassword === '' && ! $this->option('dry-run') && ! $this->option('verify-only')) {
            $this->error('--default-password is required for a full import');

            return self::FAILURE;
        }

        $res = $import->run($path, [
            'prefix' => (string) $this->option('prefix'),
            'dry_run' => (bool) $this->option('dry-run'),
            'force' => (bool) $this->option('force'),
            'verify_only' => (bool) $this->option('verify-only'),
            'since' => $this->option('since'),
            'default_password' => $defaultPassword,
            'backups_from' => $this->option('backups-from'),
        ]);

        if (! empty($res['verify_only'])) {
            foreach ($res['tables'] ?? [] as $row) {
                $flag = ! empty($row['match']) ? 'yes' : 'no';
                $this->line(sprintf(
                    '%s dump=%d db=%d match=%s',
                    $row['table'],
                    $row['dump'],
                    $row['db'],
                    $flag,
                ));
            }

            return ! empty($res['ok']) ? self::SUCCESS : self::FAILURE;
        }

        $this->info(json_encode($res, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ! empty($res['ok']) ? self::SUCCESS : self::FAILURE;
    }
}
