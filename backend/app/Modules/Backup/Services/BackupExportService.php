<?php

namespace App\Modules\Backup\Services;

use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class BackupExportService
{
    public function __construct(
        protected SettingsStore $settings,
        protected PanelClientFactory $panels,
    ) {}

    public function backupDir(): string
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        return $dir;
    }

    public function buildZip(?string $stamp = null): string
    {
        $stamp = $stamp ?: now()->format('Y-m-d_His');
        $filename = "svp-backup-{$stamp}.zip";
        $zipPath = $this->backupDir().'/'.$filename;

        $tables = $this->exportableTables();
        $data = [];
        foreach ($tables as $table) {
            $data[$table] = DB::table($table)->get()->map(fn ($r) => (array) $r)->all();
        }

        $manifest = [
            'version' => 1,
            'created_at' => now()->toIso8601String(),
            'tables' => $tables,
            'panels_expected' => 0,
            'panel_db_files' => [],
            'panel_db_failures' => [],
        ];

        $settings = DB::table('svp_settings')->get(['key_name', 'value'])->map(function ($row) {
            $key = (string) $row->key_name;
            $val = $row->value;
            if (str_contains($key, 'token') || str_contains($key, 'secret') || str_contains($key, 'password')) {
                $val = '[redacted]';
            }

            return ['key_name' => $key, 'value' => $val];
        })->all();

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create backup zip');
        }

        $zip->addFromString('laravel/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $zip->addFromString('laravel/data.json', json_encode($data, JSON_UNESCAPED_UNICODE));
        $zip->addFromString('laravel/settings.json', json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        $zip->addFromString('laravel/database.sql', $this->buildSqlDump($data));

        if (svp_modules()->isEnabled('xui_panel') && Schema::hasTable('svp_panels')) {
            $panelMeta = $this->appendPanelDatabases($zip);
            $manifest['panels_expected'] = $panelMeta['panels_expected'];
            $manifest['panel_db_files'] = $panelMeta['panel_db_files'];
            $manifest['panel_db_failures'] = $panelMeta['panel_db_failures'];
            $manifest['has_panel_db'] = $panelMeta['panel_db_files'] !== [];
            $zip->deleteName('laravel/manifest.json');
            $zip->addFromString('laravel/manifest.json', json_encode($manifest, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }

        $zip->close();

        $this->settings->set('backup_last_built_at', time());
        $this->pruneOld((int) $this->settings->get('backup_keep_count', 5));

        return $zipPath;
    }

    /** @return array<int, string> */
    public function exportableTables(): array
    {
        $out = [];
        foreach (Schema::getTableListing() as $name) {
            if (str_starts_with($name, 'svp_') || $name === 'dashboard_users') {
                $out[] = $name;
            }
        }
        sort($out);

        return $out;
    }

    /** @param  array<string, array<int, array<string, mixed>>>  $data */
    protected function buildSqlDump(array $data): string
    {
        $lines = ['-- SimpleVPBot Laravel backup'];
        foreach ($data as $table => $rows) {
            foreach ($rows as $row) {
                $cols = array_keys($row);
                $vals = array_map(fn ($v) => $this->sqlValue($v), array_values($row));
                $lines[] = 'INSERT INTO `'.$table.'` (`'.implode('`,`', $cols).'`) VALUES ('.implode(',', $vals).');';
            }
        }

        return implode("\n", $lines);
    }

    protected function sqlValue(mixed $v): string
    {
        if ($v === null) {
            return 'NULL';
        }
        if (is_bool($v)) {
            return $v ? '1' : '0';
        }
        if (is_int($v) || is_float($v)) {
            return (string) $v;
        }

        return "'".str_replace("'", "''", (string) $v)."'";
    }

    public function pruneOld(int $keep): void
    {
        $keep = max(1, $keep);
        $files = glob($this->backupDir().'/svp-backup-*.zip') ?: [];
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, $keep) as $old) {
            if (is_file($old)) {
                @unlink($old);
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listFiles(): array
    {
        $rows = [];
        foreach (glob($this->backupDir().'/svp-backup-*.zip') ?: [] as $path) {
            if (! is_file($path)) {
                continue;
            }
            $name = basename($path);
            $rows[] = [
                'filename' => $name,
                'size_bytes' => (int) filesize($path),
                'created_at' => (int) filemtime($path),
                'has_panel_db' => false,
                'panel_db_status' => 'na',
                'panel_db_detail' => '',
            ];
        }
        usort($rows, fn ($a, $b) => ($b['created_at'] ?? 0) <=> ($a['created_at'] ?? 0));

        return $rows;
    }

    public function resolvePath(string $filename): ?string
    {
        $name = basename($filename);
        if (! preg_match('/^svp-backup-[a-zA-Z0-9_-]+\.zip$/', $name)) {
            return null;
        }
        $path = $this->backupDir().'/'.$name;
        if (! is_readable($path) || ! is_file($path)) {
            return null;
        }
        $realDir = realpath($this->backupDir());
        $realPath = realpath($path);
        if ($realDir === false || $realPath === false || ! str_starts_with($realPath, $realDir)) {
            return null;
        }

        return $realPath;
    }

    /** @return array{panels_expected:int, panel_db_files:array<int,string>, panel_db_failures:array<int,array<string,mixed>>} */
    protected function appendPanelDatabases(ZipArchive $zip): array
    {
        $files = [];
        $failures = [];
        $panels = DB::table('svp_panels')->where('active', 1)->orderBy('sort_order')->orderBy('id')->get();
        foreach ($panels as $pn) {
            $pid = (int) ($pn->id ?? 0);
            if ($pid < 1) {
                continue;
            }
            $db = $this->panels->runWithPanel($pid, function ($client) {
                if (! $client->loginWithRetries(3, 250000)) {
                    return false;
                }
                if (method_exists($client, 'getDbBinaryForBackup')) {
                    return $client->getDbBinaryForBackup();
                }
                if (method_exists($client, 'getDbBinary')) {
                    return $client->getDbBinary();
                }
                if (method_exists($client, 'exportUsersSnapshot')) {
                    return json_encode($client->exportUsersSnapshot(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                }

                return false;
            }, (array) $pn);
            if (! is_string($db) || $db === '') {
                $failures[] = [
                    'panel_id' => $pid,
                    'label' => (string) ($pn->label ?? ''),
                'step' => 'download_failed',
                ];

                continue;
            }
            $name = str_starts_with($db, '{') ? 'panel-db/panel-'.$pid.'.json' : 'panel-db/panel-'.$pid.'.db';
            $zip->addFromString($name, $db);
            $files[] = $name;
        }

        return [
            'panels_expected' => count($panels),
            'panel_db_files' => $files,
            'panel_db_failures' => $failures,
        ];
    }
}
