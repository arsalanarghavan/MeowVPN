<?php

namespace App\Services\AdminQuery;

use App\Modules\XuiPanel\Services\PurgeExpiredService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgeExpiredQueryService
{
    public function __construct(
        protected SettingsStore $settings,
        protected PurgeExpiredService $purge,
    ) {}

    /** @return array<string, mixed> */
    public function list(array $params): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 20)));
        $status = (string) ($params['status'] ?? 'all');
        $graceDays = $this->purge->effectiveGraceDays();

        $items = [];
        $total = 0;
        if (Schema::hasTable('svp_services')) {
            $q = $this->baseExpiredQuery();
            if ($status === 'ready') {
                $q->where('expires_at', '<', now()->subDays($graceDays));
            } elseif ($status === 'in_grace' || $status === 'grace') {
                $q->where('expires_at', '<', now())
                    ->where('expires_at', '>=', now()->subDays($graceDays));
            } elseif ($status === 'expired') {
                // base query already filters expired xray rows
            } elseif ($status === 'active') {
                $q = DB::table('svp_services')->whereNull('deleted_at');
                $q->where(function ($sub) {
                    $sub->whereNull('expires_at')->orWhere('expires_at', '>=', now());
                });
            }
            $total = (clone $q)->count();
            $items = $q->orderBy('expires_at')->offset(($page - 1) * $perPage)->limit($perPage)->get()->all();
        }

        $panelId = (int) ($params['panel_id'] ?? 0);
        $immediate = ['count' => 0, 'ids' => []];
        if ($panelId > 0 && Schema::hasTable('svp_services')) {
            $ids = $this->baseExpiredQuery()
                ->where('panel_id', $panelId)
                ->where('expires_at', '<', now()->subDays($graceDays))
                ->limit(50)
                ->pluck('id')
                ->map(fn ($v) => (int) $v)
                ->all();
            $immediate = ['count' => count($ids), 'ids' => $ids];
        }

        return [
            'ok' => true,
            'items' => $items,
            'totals' => $this->countExpiredTotals($graceDays),
            'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total],
            'settings' => [
                'purge_expired_enabled' => $this->purge->isEnabled(),
                'purge_expired_grace_days' => $graceDays,
                'purge_expired_warn_days' => implode(',', array_map('strval', $this->purge->effectiveWarnDays())),
                'purge_expired_notify_user' => $this->purge->notifyUserEnabled(),
                'last_purge_expired_run' => $this->settings->get('last_purge_expired_run', []),
            ],
            'immediate_batch' => $immediate,
        ];
    }

    /** @return array{all:int,in_grace:int,ready:int} */
    protected function countExpiredTotals(int $graceDays): array
    {
        if (! Schema::hasTable('svp_services')) {
            return ['all' => 0, 'in_grace' => 0, 'ready' => 0];
        }
        $all = (clone $this->baseExpiredQuery())->count();
        $ready = (clone $this->baseExpiredQuery())
            ->where('expires_at', '<', now()->subDays($graceDays))
            ->count();

        return [
            'all' => $all,
            'in_grace' => max(0, $all - $ready),
            'ready' => $ready,
        ];
    }

    protected function baseExpiredQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('svp_services')
            ->whereNull('deleted_at')
            ->where('inbound_id', '>', 0)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->where(function ($q) {
                $q->whereNull('service_type')
                    ->orWhere('service_type', '')
                    ->orWhere('service_type', 'xray');
            })
            ->where(function ($q) {
                $q->whereNull('service_type')->orWhere('service_type', '!=', 'l2tp');
            });
    }
}
