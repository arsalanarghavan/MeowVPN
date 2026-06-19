<?php

namespace App\Services\AdminQuery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogsQueryService
{
    /** @return array{rows: array<int, object>, total: int} */
    public function query(int $page, int $perPage, string $level, string $q): array
    {
        if (! Schema::hasTable('svp_logs')) {
            return ['rows' => [], 'total' => 0];
        }

        $query = DB::table('svp_logs')->orderByDesc('id');
        if ($level !== '') {
            $query->where('level', $level);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('message', 'like', '%'.$q.'%')
                    ->orWhere('context_json', 'like', '%'.$q.'%');
            });
        }

        $total = (clone $query)->count();
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();

        return ['rows' => $rows, 'total' => $total];
    }
}
