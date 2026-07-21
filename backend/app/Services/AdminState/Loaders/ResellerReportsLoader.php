<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\AdminState\ResellerReportsBuilder;

class ResellerReportsLoader extends AbstractLoader
{
    public function __construct(protected ResellerReportsBuilder $builder) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsResellerReports();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $p = $ctx->page('resellerReports');
        $window = (int) ($ctx->request->query(
            'reseller_reports_window_days',
            $ctx->request->query('reseller_reports_days', 30)
        ) ?? 30);
        $q = (string) ($ctx->request->query('reseller_reports_q', '') ?? '');
        $sort = strtolower(trim((string) ($ctx->request->query('reseller_reports_sort', 'sales') ?? 'sales')));
        if ($sort === 'revenue_desc') {
            $sort = 'sales';
        }

        $scopeAncestor = null;
        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            $scopeAncestor = $ctx->actorSvpUserId;
        } elseif ($ctx->resellerContextId > 0) {
            $scopeAncestor = $ctx->resellerContextId;
        }

        $built = $this->builder->build(
            $window,
            $q,
            $sort,
            [
                'page' => (int) ($p['page'] ?? 1),
                'per_page' => (int) ($p['per_page'] ?? 25),
                'offset' => (int) ($p['offset'] ?? 0),
            ],
            $scopeAncestor,
        );

        $rows = is_array($built['rows'] ?? null) ? $built['rows'] : [];
        $result->setTotal('resellerReports', (int) ($built['total'] ?? 0));
        $result->merge([
            'resellerReports' => $rows,
            'resellerReportsRows' => $rows,
            'resellerReportsStats' => [
                'window_days' => (int) ($built['window_days'] ?? 30),
                'since' => (string) ($built['since'] ?? ''),
                'backfill_done' => (bool) ($built['backfill_done'] ?? true),
                'daily_scoped' => (bool) ($built['daily_scoped'] ?? false),
                'summary' => is_array($built['summary'] ?? null) ? $built['summary'] : [],
            ],
            'resellerReportsDaily' => is_array($built['daily'] ?? null) ? $built['daily'] : [],
        ]);
    }
}
