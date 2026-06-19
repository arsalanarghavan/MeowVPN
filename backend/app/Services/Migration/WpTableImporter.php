<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WpTableImporter
{
    /** @return array{inserted:int, skipped:int, tables:int} */
    public function import(array $tables, bool $force = false, bool $dryRun = false, ?string $since = null): array
    {
        $sinceTs = $since !== null && $since !== '' ? strtotime($since.' UTC') : false;
        $inserted = 0;
        $skipped = 0;
        $tableCount = 0;

        foreach ($tables as $table => $rows) {
            if (! str_starts_with($table, 'svp_') || ! Schema::hasTable($table)) {
                continue;
            }
            $tableCount++;
            if ($dryRun) {
                $inserted += count($rows);
                continue;
            }

            DB::transaction(function () use ($table, $rows, $force, &$inserted, &$skipped) {
                $cols = Schema::getColumnListing($table);
                foreach ($rows as $row) {
                    if ($sinceTs !== false && ! $this->rowIsSince($row, $sinceTs)) {
                        $skipped++;
                        continue;
                    }
                    $row = $this->filterRow($row, $cols);
                    if ($row === []) {
                        $skipped++;
                        continue;
                    }
                    $id = isset($row['id']) ? (int) $row['id'] : 0;
                    if ($id > 0 && DB::table($table)->where('id', $id)->exists()) {
                        if ($force) {
                            DB::table($table)->where('id', $id)->update($row);
                            $inserted++;
                        } else {
                            $skipped++;
                        }
                        continue;
                    }
                    if ($id > 0) {
                        DB::table($table)->insert($row);
                    } else {
                        DB::table($table)->insert($row);
                    }
                    $inserted++;
                }
            });
        }

        return ['inserted' => $inserted, 'skipped' => $skipped, 'tables' => $tableCount];
    }

    /** @param  array<string, mixed>  $row */
    /** @return array<string, mixed> */
    protected function filterRow(array $row, array $cols): array
    {
        $out = [];
        foreach ($row as $k => $v) {
            if (in_array($k, $cols, true)) {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    /** @param  array<string, mixed>  $row */
    protected function rowIsSince(array $row, int $sinceTs): bool
    {
        foreach (['updated_at', 'created_at'] as $col) {
            if (empty($row[$col])) {
                continue;
            }
            $ts = strtotime((string) $row[$col].' UTC');

            return $ts !== false && $ts >= $sinceTs;
        }

        return true;
    }
}
