<?php

namespace App\Services;

use App\Models\DashboardUser;
use App\Services\AdminState\AdminActorResolver;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\AdminState\Loaders\AuditLoader;
use App\Services\AdminState\Loaders\BackupLoader;
use App\Services\AdminState\Loaders\BotUiLoader;
use App\Services\AdminState\Loaders\BotsLoader;
use App\Services\AdminState\Loaders\BroadcastsLoader;
use App\Services\AdminState\Loaders\CatalogLoader;
use App\Services\AdminState\Loaders\DiscountsLoader;
use App\Services\AdminState\Loaders\MarketingLoader;
use App\Services\AdminState\Loaders\MonitoringLoader;
use App\Services\AdminState\Loaders\OverviewLoader;
use App\Services\AdminState\Loaders\PanelFinancialReportsLoader;
use App\Services\AdminState\Loaders\PaymentsLoader;
use App\Services\AdminState\Loaders\ReferralLoader;
use App\Services\AdminState\Loaders\ResellerExtrasLoader;
use App\Services\AdminState\Loaders\ResellerReportsLoader;
use App\Services\AdminState\Loaders\ResellersLoader;
use App\Services\AdminState\Loaders\SettingsLoader;
use App\Services\AdminState\Loaders\TextsLoader;
use App\Services\AdminState\Loaders\UiMetadataLoader;
use App\Services\AdminState\Loaders\UnitEconomicsLoader;
use App\Services\AdminState\Loaders\UsersLoader;
use App\Services\AdminState\Loaders\XrayCoreLoader;
use App\Services\AdminState\Loaders\PanelTemplatesLoader;
use App\Services\AdminState\PaginationBuilder;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;

class AdminStateBuilder
{
    /** @param  array<int, object>  $loaders */
    public function __construct(
        protected AdminActorResolver $actorResolver,
        protected PaginationBuilder $paginationBuilder,
        protected SettingsLoader $settingsLoader,
        protected UiMetadataLoader $uiMetadataLoader,
        protected CatalogLoader $catalogLoader,
        protected UsersLoader $usersLoader,
        protected ResellersLoader $resellersLoader,
        protected PaymentsLoader $paymentsLoader,
        protected PanelFinancialReportsLoader $panelFinancialReportsLoader,
        protected OverviewLoader $overviewLoader,
        protected MonitoringLoader $monitoringLoader,
        protected BroadcastsLoader $broadcastsLoader,
        protected DiscountsLoader $discountsLoader,
        protected TextsLoader $textsLoader,
        protected BotsLoader $botsLoader,
        protected BotUiLoader $botUiLoader,
        protected ReferralLoader $referralLoader,
        protected ResellerReportsLoader $resellerReportsLoader,
        protected MarketingLoader $marketingLoader,
        protected UnitEconomicsLoader $unitEconomicsLoader,
        protected ResellerExtrasLoader $resellerExtrasLoader,
        protected XrayCoreLoader $xrayCoreLoader,
        protected PanelTemplatesLoader $panelTemplatesLoader,
        protected BackupLoader $backupLoader,
        protected AuditLoader $auditLoader,
    ) {}

    /** @return array<string, mixed> */
    public function build(?Authenticatable $actor, Request $request): array
    {
        if (! $actor instanceof DashboardUser) {
            return ['ok' => false, 'message' => 'forbidden'];
        }

        $ctx = AdminStateContext::fromRequest($request, $actor);
        $this->actorResolver->applyScope($ctx);

        $result = new AdminStateResult;

        foreach ($this->loaders() as $loader) {
            $loader->loadIfNeeded($ctx, $result);
        }

        $payload = $result->data;
        $payload['pagination'] = $this->paginationBuilder->build($ctx, $result);
        $payload['resellerContextId'] = $ctx->resellerContextId;

        if ($ctx->isReseller) {
            $payload['user'] = ['label' => $actor->username];
        }

        return $payload;
    }

    /** @return array<int, object> */
    protected function loaders(): array
    {
        return [
            $this->settingsLoader,
            $this->uiMetadataLoader,
            $this->overviewLoader,
            $this->catalogLoader,
            $this->usersLoader,
            $this->resellersLoader,
            $this->paymentsLoader,
            $this->panelFinancialReportsLoader,
            $this->monitoringLoader,
            $this->broadcastsLoader,
            $this->discountsLoader,
            $this->textsLoader,
            $this->botsLoader,
            $this->botUiLoader,
            $this->referralLoader,
            $this->resellerReportsLoader,
            $this->marketingLoader,
            $this->unitEconomicsLoader,
            $this->resellerExtrasLoader,
            $this->xrayCoreLoader,
            $this->panelTemplatesLoader,
            $this->backupLoader,
            $this->auditLoader,
        ];
    }
}
