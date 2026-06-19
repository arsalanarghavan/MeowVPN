<?php

namespace App\Services\AdminState;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class UserListQuery
{
    public static function applySearch(Builder $query, string $q, string $alias = 'svp_users'): void
    {
        $q = trim($q);
        if ($q === '') {
            return;
        }
        if (strlen($q) > 128) {
            $q = substr($q, 0, 128);
        }

        if (preg_match('/^\d+$/', $q)) {
            $n = (int) $q;
            $query->where(function (Builder $w) use ($n) {
                $w->where('id', $n)->orWhere('tg_user_id', $n)->orWhere('bale_user_id', $n);
            });

            return;
        }

        $u = ltrim($q, '@');
        $like = '%'.$u.'%';
        $query->where(function (Builder $w) use ($like, $q) {
            $w->where('username', 'like', $like)
                ->orWhereRaw("CONCAT(COALESCE(first_name,''),' ',COALESCE(last_name,'')) LIKE ?", [$like]);
            $digits = preg_replace('/\D+/', '', $q);
            if ($digits !== '') {
                $w->orWhere('phone', 'like', '%'.$digits.'%');
            }
        });
    }

    /** @return array{needs_svc_join: bool} */
    public static function applyListFilters(Builder $query, Request $request, bool $forPending = false): array
    {
        $needsSvcJoin = false;

        if (! $forPending) {
            $status = (string) $request->query('users_status', 'all');
            if ($status !== 'all' && in_array($status, ['pending', 'approved', 'rejected', 'blocked'], true)) {
                $query->where('status', $status);
            }
        } else {
            $query->where('status', 'pending');
        }

        $role = (string) $request->query('users_role', 'all');
        if ($role !== 'all' && in_array($role, ['user', 'reseller', 'admin'], true)) {
            $query->where('role', $role);
        }

        $platform = (string) $request->query('users_platform', 'all');
        match ($platform) {
            'telegram' => $query->where('tg_user_id', '>', 0),
            'bale' => $query->where('bale_user_id', '>', 0),
            'both' => $query->where('tg_user_id', '>', 0)->where('bale_user_id', '>', 0),
            'none' => $query->where(fn ($w) => $w->whereNull('tg_user_id')->orWhere('tg_user_id', 0))
                ->where(fn ($w) => $w->whereNull('bale_user_id')->orWhere('bale_user_id', 0)),
            default => null,
        };

        $df = trim((string) $request->query('users_date_from', ''));
        if ($df !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $df)) {
            $query->where('created_at', '>=', substr($df, 0, 10).' 00:00:00');
        }
        $dt = trim((string) $request->query('users_date_to', ''));
        if ($dt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $dt)) {
            $query->where('created_at', '<=', substr($dt, 0, 10).' 23:59:59');
        }

        $sort = (string) $request->query('users_sort', 'created_desc');
        match ($sort) {
            'created_asc' => $query->orderBy('created_at')->orderBy('id'),
            'id_desc' => $query->orderByDesc('id'),
            'id_asc' => $query->orderBy('id'),
            'status_asc' => $query->orderBy('status')->orderByDesc('id'),
            'status_desc' => $query->orderByDesc('status')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return ['needs_svc_join' => $needsSvcJoin];
    }

    /** @param  array<int, int>  $scopeIds */
    public static function applyScope(Builder $query, array $scopeIds): void
    {
        if ($scopeIds === []) {
            $query->whereRaw('1=0');

            return;
        }
        $query->whereIn('id', $scopeIds);
    }
}
