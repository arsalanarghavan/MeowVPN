<?php

namespace App\Services\Migration;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WpImportVerifier
{
    /** @return array{ok: bool, tables: list<array{table: string, dump: int, db: int, match: bool}>} */
    public function verify(WpDumpData $data): array
    {
        $rows = [];
        $allMatch = true;
        foreach ($data->tableCounts() as $table => $dumpCount) {
            if (! Schema::hasTable($table)) {
                $rows[] = ['table' => $table, 'dump' => $dumpCount, 'db' => 0, 'match' => false];
                $allMatch = false;
                continue;
            }
            $dbCount = (int) DB::table($table)->count();
            $match = $dbCount === $dumpCount;
            if (! $match) {
                $allMatch = false;
            }
            $rows[] = ['table' => $table, 'dump' => $dumpCount, 'db' => $dbCount, 'match' => $match];
        }

        return ['ok' => $allMatch, 'tables' => $rows];
    }
}
