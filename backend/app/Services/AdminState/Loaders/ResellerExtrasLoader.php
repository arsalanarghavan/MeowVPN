<?php

namespace App\Services\AdminState\Loaders;

use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\AdminQuery\ResellerCustomerChargesService;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use Illuminate\Support\Facades\DB;

class ResellerExtrasLoader extends AbstractLoader
{
    public function __construct(
        protected ResellerCustomerChargesService $charges,
        protected ResellerScopeService $scope,
    ) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsResellerExtras();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $actorId = $ctx->actorSvpUserId;
        $panelPrices = [];
        $botMap = [];

        if ($this->tableExists('svp_reseller_panel_prices')) {
            $rows = DB::table('svp_reseller_panel_prices')
                ->where('reseller_svp_user_id', $actorId)
                ->get();
            foreach ($rows as $row) {
                $panelPrices[(int) $row->panel_id] = (array) $row;
            }
        }

        if ($this->tableExists('svp_reseller_bot_profiles')) {
            $bots = DB::table('svp_reseller_bot_profiles')
                ->where('reseller_svp_user_id', $actorId)
                ->get();
            foreach ($bots as $bot) {
                $botMap[(int) $bot->id] = (array) $bot;
            }
        }

        $result->merge([
            'resellerPanelPricesMap' => $panelPrices,
            'resellerBotMap' => $botMap,
            'resellerPlanFloors' => [],
            'wholesaleCatalogByPanel' => [],
            'wholesaleLinesCatalog' => [],
            'wholesaleLines' => [],
            'resellerWholesaleLineIdsMap' => [],
        ]);

        if ($ctx->activeTab === 'reseller_charge') {
            $req = $ctx->request;
            $page = max(1, (int) $req->query('customerChargesPage', 1));
            $per = max(10, min(120, (int) $req->query('customerChargesPerPage', 50)));
            $type = (string) $req->query('customerChargesType', '');
            $type = in_array($type, ['purchase', 'renew', 'volume', 'topup'], true) ? $type : '';
            $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $req->query('customerChargesDateFrom', '')) ? (string) $req->query('customerChargesDateFrom') : '';
            $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $req->query('customerChargesDateTo', '')) ? (string) $req->query('customerChargesDateTo') : '';
            $chargeData = $this->charges->build(
                $actorId,
                $this->scope->downlineUserIds($actorId),
                $page,
                $per,
                $type,
                $from,
                $to,
            );
            $result->merge([
                'resellerCustomerCharges' => $chargeData['rows'],
                'resellerCustomerChargesPagination' => $chargeData['pagination'],
            ]);
        }
    }
}
