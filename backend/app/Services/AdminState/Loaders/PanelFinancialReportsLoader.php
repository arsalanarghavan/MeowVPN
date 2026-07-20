<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\PanelFinancialReportsService;

class PanelFinancialReportsLoader extends AbstractLoader
{
    public function __construct(protected PanelFinancialReportsService $reports) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsPanelFinancialReports() && $ctx->isAdmin;
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $req = $ctx->request;
        $result->merge([
            'panelFinancialReports' => $this->reports->build([
                'date_from' => (string) ($req->query('date_from') ?: $req->query('panel_financial_date_from', '')),
                'date_to' => (string) ($req->query('date_to') ?: $req->query('panel_financial_date_to', '')),
                'calendar' => (string) ($req->query('calendar') ?: $req->query('panel_financial_calendar', 'gregorian')),
            ]),
        ]);
    }
}
