<?php

namespace App\Services\AdminState\Loaders;

use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\DashboardBootBuilder;
use App\Services\NavTabsBuilder;
use App\Services\PortalPagesBuilder;

class UiMetadataLoader extends AbstractLoader
{
    public function __construct(
        protected NavTabsBuilder $navTabs,
        protected DashboardBootBuilder $bootBuilder,
        protected PortalPagesBuilder $portalPages,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return true;
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $l2tp = $ctx->l2tpEnabled();
        $tabs = $this->navTabs->build($l2tp);
        if ($ctx->isReseller) {
            $allowed = $this->bootBuilder->resellerAllowedTabsMap($ctx->actor);
            $tabs = $this->navTabs->filterForReseller($tabs, $allowed);
        }

        $result->merge([
            'navTabs' => $tabs,
            'portalPages' => $pages = $this->portalPages->build($ctx->isReseller),
            'wpPages' => $pages,
            'uiLayout' => $ctx->isReseller
                ? ['version' => 0, 'surfaces' => []]
                : ($result->data['uiLayout'] ?? ['version' => 0, 'surfaces' => []]),
            'uiRegistry' => $ctx->isReseller
                ? ['version' => 0, 'surfaces' => []]
                : ($result->data['uiRegistry'] ?? ['version' => 0, 'surfaces' => []]),
        ]);

        if ($ctx->isReseller) {
            $boot = $this->bootBuilder->bootstrapApiPayload($ctx->actor);
            $result->merge([
                'resellerAllowedTabs' => $this->bootBuilder->resellerAllowedTabsMap($ctx->actor),
                'actorPermissions' => $boot['actorPermissions'] ?? null,
            ]);
        }
    }
}
