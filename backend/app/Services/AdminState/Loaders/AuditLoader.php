<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminQuery\AuditQueryService;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;

class AuditLoader extends AbstractLoader
{
    public function __construct(protected AuditQueryService $audit) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->activeTab === 'audit';
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $request = $ctx->request;
        $page = max(1, (int) $request->query('audit_page', $request->query('page', 1)));
        $perPage = max(1, min(100, (int) $request->query('audit_per_page', $request->query('per_page', 30))));
        $res = $this->audit->query(
            (string) $request->query('domain', ''),
            (string) $request->query('event_type', ''),
            (string) $request->query('q', ''),
            $page,
            $perPage,
        );

        $result->merge([
            'auditRows' => array_map(fn ($row) => (array) $row, $res['rows']),
            'auditPagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $res['total']],
        ]);
    }
}
