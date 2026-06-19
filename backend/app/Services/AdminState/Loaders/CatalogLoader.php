<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpCard;
use App\Models\SvpL2tpServer;
use App\Models\SvpPanel;
use App\Models\SvpPlan;
use App\Models\SvpPlanCategory;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;

class CatalogLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsCatalog();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $panels = $this->loadPanels($ctx, $result);
        $plans = $this->loadPlans($ctx, $result);
        $planCategories = $this->loadPlanCategories($ctx, $result);
        $cards = $this->loadCards($ctx, $result);
        $l2tp = $ctx->l2tpEnabled() ? $this->loadL2tp($ctx, $result) : [];

        $result->merge([
            'panels' => $panels,
            'plans' => $plans,
            'planCategories' => $planCategories,
            'cards' => $cards,
            'l2tpServers' => $l2tp,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    protected function loadPanels(AdminStateContext $ctx, AdminStateResult $result): array
    {
        if (! $this->tableExists('svp_panels')) {
            return [];
        }

        $p = $ctx->page('panels');
        $q = SvpPanel::query()->orderBy('sort_order')->orderBy('id');
        if ($ctx->allowedPanelIds !== []) {
            $q->whereIn('id', $ctx->allowedPanelIds);
        } elseif ($ctx->isReseller) {
            $result->setTotal('panels', 0);

            return [];
        }

        $total = (clone $q)->count();
        $result->setTotal('panels', $total);

        return $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));
    }

    /** @return array<int, array<string, mixed>> */
    protected function loadPlans(AdminStateContext $ctx, AdminStateResult $result): array
    {
        if (! $this->tableExists('svp_plans')) {
            return [];
        }

        $p = $ctx->page('plans');
        $q = SvpPlan::query()->orderByDesc('id');
        if ($ctx->allowedPanelIds !== []) {
            $q->whereIn('panel_id', $ctx->allowedPanelIds);
        } elseif ($ctx->isReseller) {
            $result->setTotal('plans', 0);

            return [];
        }

        $total = (clone $q)->count();
        $result->setTotal('plans', $total);
        $rows = $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));

        if (! $ctx->l2tpEnabled()) {
            $rows = array_values(array_filter($rows, fn ($r) => ($r['service_type'] ?? 'xray') !== 'l2tp'));
        }

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    protected function loadPlanCategories(AdminStateContext $ctx, AdminStateResult $result): array
    {
        if (! $this->tableExists('svp_plan_categories')) {
            return [];
        }

        $p = $ctx->page('planCategories');
        $q = SvpPlanCategory::query()->orderBy('sort_order')->orderBy('id');
        $total = (clone $q)->count();
        $result->setTotal('planCategories', $total);

        return $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));
    }

    /** @return array<int, array<string, mixed>> */
    protected function loadCards(AdminStateContext $ctx, AdminStateResult $result): array
    {
        if (! $this->tableExists('svp_cards')) {
            return [];
        }

        $p = $ctx->page('cards');
        $q = SvpCard::query()->orderByDesc('priority')->orderByDesc('id');
        if ($ctx->isReseller && $ctx->actorSvpUserId > 0) {
            $q->where('owner_svp_user_id', $ctx->actorSvpUserId);
        } elseif ($ctx->resellerContextId > 0) {
            $q->where('owner_svp_user_id', $ctx->resellerContextId);
        } else {
            $q->where('owner_svp_user_id', 0);
        }

        $total = (clone $q)->count();
        $result->setTotal('cards', $total);

        return $this->fetchRows((clone $q)->offset($p['offset'])->limit($p['per_page']));
    }

    /** @return array<int, array<string, mixed>> */
    protected function loadL2tp(AdminStateContext $ctx, AdminStateResult $result): array
    {
        if (! $this->tableExists('svp_l2tp_servers')) {
            return [];
        }

        $p = $ctx->page('l2tp');
        $q = SvpL2tpServer::query()->orderBy('id');
        $total = (clone $q)->count();
        $result->setTotal('l2tp', $total);

        $svc = app(\App\Modules\L2tp\Services\L2tpServerService::class);
        $rows = [];
        foreach ((clone $q)->offset($p['offset'])->limit($p['per_page'])->get() as $row) {
            $rows[] = $svc->toAdminPayload($row);
        }

        return $rows;
    }
}
