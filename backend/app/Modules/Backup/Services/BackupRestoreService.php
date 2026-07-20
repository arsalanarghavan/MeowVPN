<?php

namespace App\Modules\Backup\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ZipArchive;

class BackupRestoreService
{
    public function __construct(
        protected BackupMergeRestore $mergeRestore,
    ) {}

    /** @return array{ok:bool, message?:string, data?:array<string, mixed>} */
    public function restoreFromZip(string $path, bool $restorePanelDb = false): array
    {
        if (! is_readable($path)) {
            return ['ok' => false, 'message' => 'فایل در دسترس نیست.'];
        }

        $parsed = $this->parseZip($path);
        if ($parsed === null) {
            return ['ok' => false, 'message' => 'باز کردن یا خواندن زیپ ناموفق بود.'];
        }

        $stats = $this->mergeRestore->restoreMerge($parsed['tables']);
        foreach ($parsed['parse_errors'] as $error) {
            $stats['errors'][] = $error;
        }
        if ($restorePanelDb) {
            $panelRestore = $this->restorePanelDatabasesFromZip($path, $parsed['manifest'] ?? []);
            $stats['panel_db_restored'] = $panelRestore['restored'] ?? 0;
            $stats['panel_db_errors'] = $panelRestore['errors'] ?? [];
            $stats['panel_db_skipped'] = $panelRestore['skipped'] ?? [];
            if (($panelRestore['restored'] ?? 0) < 1 && ($panelRestore['errors'] ?? []) !== []) {
                $stats['panel_db_note'] = 'panel_db_partial_or_failed';
            } elseif (($panelRestore['restored'] ?? 0) > 0) {
                $stats['panel_db_note'] = 'panel_db_restored';
            } elseif (($panelRestore['skipped'] ?? []) !== []) {
                $stats['panel_db_note'] = 'panel_db_skipped_only';
            } else {
                $stats['panel_db_note'] = 'panel_db_not_in_zip';
            }
        }

        return [
            'ok' => true,
            'message' => $restorePanelDb
                ? 'بازگردانی انجام شد (ادغامی؛ DB پنل XUI در صورت وجود در زیپ وارد می‌شود).'
                : 'بازگردانی ادغامی انجام شد. دادهٔ قبلی حفظ شد.',
            'data' => $stats,
        ];
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $dumpByTable
     * @return array<string, mixed>
     */
    public function restoreMerge(array $dumpByTable): array
    {
        return $this->mergeRestore->restoreMerge($dumpByTable);
    }

    /** @return array{tables: array<string, array<int, array<string, mixed>>>, parse_errors: array<int, array<string, mixed>>, manifest: array<string, mixed>}|null */
    protected function parseZip(string $path): ?array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            return null;
        }

        $manifest = [];
        $manifestRaw = $zip->getFromName('laravel/manifest.json');
        if (is_string($manifestRaw) && $manifestRaw !== '') {
            $decoded = json_decode($manifestRaw, true);
            if (is_array($decoded)) {
                $manifest = $decoded;
            }
        }

        $json = $zip->getFromName('laravel/data.json');
        if (is_string($json) && $json !== '') {
            $data = json_decode($json, true);
            $zip->close();
            if (is_array($data)) {
                return [
                    'tables' => $this->normalizeDumpKeys($data),
                    'parse_errors' => [],
                    'manifest' => $manifest,
                ];
            }
        }

        foreach (['laravel/database.sql'] as $sqlPath) {
            $sql = $zip->getFromName($sqlPath);
            if (is_string($sql) && $sql !== '') {
                $parsed = $this->parseSqlDump($sql);
                $zip->close();

                return [
                    'tables' => $parsed['tables'],
                    'parse_errors' => $parsed['errors'],
                    'manifest' => $manifest,
                ];
            }
        }

        $zip->close();

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, array<int, array<string, mixed>>>
     */
    protected function normalizeDumpKeys(array $data): array
    {
        $out = [];
        foreach ($data as $table => $rows) {
            $name = $this->normalizeTableName((string) $table);
            if ($name === '' || ! is_array($rows)) {
                continue;
            }
            $out[$name] = array_map(fn ($r) => is_array($r) ? $r : (array) $r, $rows);
        }

        return $out;
    }

    /**
     * @return array{tables: array<string, array<int, array<string, mixed>>>, errors: array<int, array<string, mixed>>}
     */
    protected function parseSqlDump(string $sql): array
    {
        $out = [];
        $errors = [];
        foreach (preg_split('/;\s*\n/', $sql) as $stmtIndex => $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || ! str_starts_with(strtoupper($stmt), 'INSERT INTO')) {
                continue;
            }
            if (! preg_match('/^INSERT INTO `([^`]+)` \(([^)]+)\) VALUES \((.+)\)$/s', $stmt, $m)) {
                $errors[] = [
                    'reason' => 'sql_insert_parse_failed',
                    'statement_index' => $stmtIndex,
                    'preview' => substr($stmt, 0, 120),
                ];
                continue;
            }
            $table = $this->normalizeTableName($m[1]);
            $cols = array_map(fn ($c) => trim($c, " `\t\n\r"), explode(',', $m[2]));
            $vals = $this->parseSqlValues($m[3]);
            if (count($cols) !== count($vals)) {
                $errors[] = [
                    'reason' => 'sql_column_value_mismatch',
                    'table' => $table,
                    'columns' => count($cols),
                    'values' => count($vals),
                ];
                continue;
            }
            $row = array_combine($cols, $vals);
            if ($row === false) {
                $errors[] = [
                    'reason' => 'sql_row_combine_failed',
                    'table' => $table,
                ];
                continue;
            }
            $out[$table][] = $row;
        }

        return ['tables' => $out, 'errors' => $errors];
    }

    /** @return array<int, mixed> */
    protected function parseSqlValues(string $chunk): array
    {
        $vals = [];
        $cur = '';
        $inStr = false;
        $len = strlen($chunk);
        for ($i = 0; $i < $len; $i++) {
            $ch = $chunk[$i];
            if ($inStr) {
                if ($ch === "'" && ($i + 1 < $len && $chunk[$i + 1] === "'")) {
                    $cur .= "'";
                    $i++;
                } elseif ($ch === "'") {
                    $inStr = false;
                } else {
                    $cur .= $ch;
                }
                continue;
            }
            if ($ch === "'") {
                $inStr = true;
                $cur = '';
                continue;
            }
            if ($ch === ',') {
                $vals[] = $this->castSqlToken(trim($cur));
                $cur = '';
                continue;
            }
            $cur .= $ch;
        }
        if ($cur !== '' || str_ends_with($chunk, ',')) {
            $vals[] = $this->castSqlToken(trim($cur));
        }

        return $vals;
    }

    protected function castSqlToken(string $token): mixed
    {
        if (strtoupper($token) === 'NULL') {
            return null;
        }
        if ($token === '1' || $token === '0') {
            return $token === '1' ? 1 : 0;
        }
        if (is_numeric($token)) {
            return str_contains($token, '.') ? (float) $token : (int) $token;
        }

        return $token;
    }

    protected function normalizeTableName(string $table): string
    {
        $table = trim($table);
        if (preg_match('/svp_[a-z0-9_]+$/', $table, $m)) {
            return $m[0];
        }

        return $table;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array{restored:int, errors:array<int,array<string,mixed>>, skipped:array<int,array<string,mixed>>}
     */
    protected function restorePanelDatabasesFromZip(string $zipPath, array $manifest = []): array
    {
        if (! svp_modules()->isEnabled('xui_panel') || ! class_exists(\App\Modules\PasarGuard\Services\PanelClientFactory::class)) {
            return ['restored' => 0, 'errors' => [['step' => 'module_disabled']], 'skipped' => []];
        }
        $zip = new ZipArchive;
        if ($zip->open($zipPath) !== true) {
            return ['restored' => 0, 'errors' => [['step' => 'zip_open_failed']], 'skipped' => []];
        }

        $names = [];
        if (! empty($manifest['panel_db_files']) && is_array($manifest['panel_db_files'])) {
            foreach ($manifest['panel_db_files'] as $n) {
                $n = (string) $n;
                if ($n !== '') {
                    $names[] = $n;
                }
            }
        }
        if ($names === []) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = (string) $zip->getNameIndex($i);
                if (preg_match('#^panel-db/panel-(\d+)\.(db|json)$#', $name)) {
                    $names[] = $name;
                }
            }
        }
        $names = array_values(array_unique($names));

        $restored = 0;
        $errors = [];
        $skipped = [];
        $factory = app(\App\Modules\PasarGuard\Services\PanelClientFactory::class);
        $tmpdir = rtrim(sys_get_temp_dir(), '/').'/svp-restore-panel-'.bin2hex(random_bytes(4)).'/';
        if (! is_dir($tmpdir)) {
            mkdir($tmpdir, 0755, true);
        }

        foreach ($names as $zipName) {
            if (! preg_match('#^panel-db/panel-(\d+)\.(db|json)$#', $zipName, $m)) {
                continue;
            }
            $panelId = (int) $m[1];
            $ext = (string) $m[2];
            $raw = (string) $zip->getFromName($zipName);
            if ($raw === '') {
                $errors[] = ['zip_name' => $zipName, 'panel_id' => $panelId, 'step' => 'extract'];

                continue;
            }

            $isPgSnapshot = $ext === 'json';
            if (! $isPgSnapshot) {
                $trim = ltrim($raw);
                if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
                    $probe = json_decode($trim, true);
                    $isPgSnapshot = is_array($probe) && ($probe['provider'] ?? '') === 'pasarguard';
                }
            }
            if ($isPgSnapshot) {
                $skipped[] = [
                    'zip_name' => $zipName,
                    'panel_id' => $panelId,
                    'step' => 'pg_restore_not_supported',
                    'message' => 'pasarguard_snapshot',
                ];

                continue;
            }

            if ($panelId >= 1 && Schema::hasTable('svp_panels')) {
                $panelRow = DB::table('svp_panels')->where('id', $panelId)->first();
                if ($panelRow && (string) ($panelRow->panel_provider ?? '') === 'pasarguard') {
                    $skipped[] = [
                        'zip_name' => $zipName,
                        'panel_id' => $panelId,
                        'step' => 'pg_restore_not_supported',
                        'message' => 'pasarguard_panel',
                    ];

                    continue;
                }
            }

            $local = $tmpdir.basename($zipName);
            if (file_put_contents($local, $raw) === false) {
                $errors[] = ['zip_name' => $zipName, 'panel_id' => $panelId, 'step' => 'write'];

                continue;
            }

            $res = $factory->runWithPanel($panelId, function ($client) use ($local) {
                if (! method_exists($client, 'importDbFromPath')) {
                    return ['ok' => false, 'message' => 'unsupported', 'step' => 'unsupported'];
                }
                if (! $client->loginWithRetries(6, 300000)) {
                    return ['ok' => false, 'message' => 'login_fail', 'step' => 'login'];
                }

                return array_merge(
                    ['step' => 'import'],
                    $client->importDbFromPath($local)
                );
            });

            @unlink($local);
            if (! empty($res['ok'])) {
                $restored++;
            } else {
                $errors[] = [
                    'zip_name' => $zipName,
                    'panel_id' => $panelId,
                    'step' => (string) ($res['step'] ?? 'import'),
                    'message' => (string) ($res['message'] ?? 'import_failed'),
                ];
            }
        }

        $zip->close();
        foreach (glob($tmpdir.'*') ?: [] as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        @rmdir($tmpdir);

        return ['restored' => $restored, 'errors' => $errors, 'skipped' => $skipped];
    }
}
