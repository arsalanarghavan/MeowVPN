<?php

namespace App\Services\AdminQuery;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResellerCustomerChargesService
{
    /** @param  array<int, int>  $scopeUserIds
     * @return array{rows: array<int, array<string, mixed>>, pagination: array<string, int>}
     */
    public function build(int $actorUid, array $scopeUserIds, int $page, int $perPage, string $typeFilter = '', string $dateFrom = '', string $dateTo = ''): array
    {
        if ($actorUid < 1 || $scopeUserIds === [] || ! Schema::hasTable('svp_transactions')) {
            return ['rows' => [], 'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => 0]];
        }

        $q = DB::table('svp_transactions as t')
            ->whereIn('t.user_id', $scopeUserIds)
            ->where('t.status', 'approved')
            ->where('t.billing_reseller_svp_id', $actorUid);

        if ($typeFilter !== '' && in_array($typeFilter, ['purchase', 'renew', 'volume', 'topup'], true)) {
            $q->where('t.type', $typeFilter);
        }
        if ($dateFrom !== '') {
            $q->whereDate('t.created_at', '>=', $dateFrom);
        }
        if ($dateTo !== '') {
            $q->whereDate('t.created_at', '<=', $dateTo);
        }

        $total = (clone $q)->count();
        $rows = $q->orderByDesc('t.id')->offset(($page - 1) * $perPage)->limit($perPage)->get();
        $labels = SvpUser::query()->whereIn('id', $rows->pluck('user_id')->all())->get()->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $u = $labels->get($uid);
            $out[] = [
                'id' => (int) $row->id,
                'user_id' => $uid,
                'user_label' => $u ? (string) ($u->username ?: '#'.$uid) : '#'.$uid,
                'type' => (string) ($row->type ?? ''),
                'amount' => (float) ($row->amount ?? 0),
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        return [
            'rows' => $out,
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
        ];
    }
}
