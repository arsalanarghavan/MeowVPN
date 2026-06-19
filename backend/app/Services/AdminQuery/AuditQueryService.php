<?php

namespace App\Services\AdminQuery;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditQueryService
{
    /** @return array{rows: array<int, object>, total: int} */
    public function query(string $domain, string $eventType, string $q, int $page, int $perPage): array
    {
        if (! Schema::hasTable('svp_audit_log')) {
            return ['rows' => [], 'total' => 0];
        }

        $query = DB::table('svp_audit_log')->orderByDesc('id');
        if ($domain !== '') {
            $query->where('domain', $domain);
        }
        if ($eventType !== '') {
            $query->where('event_type', $eventType);
        }
        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('event_type', 'like', '%'.$q.'%')
                    ->orWhere('actor_label', 'like', '%'.$q.'%')
                    ->orWhere('payload_json', 'like', '%'.$q.'%');
            });
        }

        $total = (clone $query)->count();
        $rows = $query->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();

        return ['rows' => $rows, 'total' => $total];
    }
}
