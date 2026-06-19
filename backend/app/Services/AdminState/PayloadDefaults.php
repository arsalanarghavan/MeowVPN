<?php

namespace App\Services\AdminState;

class PayloadDefaults
{
    /** @return array<string, mixed> */
    public static function root(): array
    {
        return [
            'settings' => [],
            'paymentMethods' => null,
            'textDefaults' => [],
            'uiLayout' => ['version' => 0, 'surfaces' => []],
            'uiRegistry' => ['version' => 0, 'surfaces' => []],
            'referralStats' => null,
            'referralEvents' => [],
            'resellerReportsStats' => null,
            'resellerReportsRows' => [],
            'resellerReportsDaily' => [],
            'marketingLifecycleStats' => null,
            'marketingLifecycleFunnel' => [],
            'marketingRules' => [],
            'marketingRuleStats' => [],
            'marketingOffers' => [],
            'panels' => [],
            'plans' => [],
            'planCategories' => [],
            'cards' => [],
            'l2tpServers' => [],
            'texts' => [],
            'discountCodes' => [],
            'discountUsageSummary' => [
                'total_redemptions' => 0,
                'total_discount_toman' => 0.0,
                'active_codes' => 0,
            ],
            'pendingUsers' => [],
            'usersList' => [],
            'resellers' => [],
            'resellerPermissionsMap' => [],
            'resellerPanelPricesMap' => [],
            'resellerBotMap' => [],
            'wholesaleCatalogByPanel' => [],
            'wholesaleLinesCatalog' => [],
            'wholesaleLines' => [],
            'resellerWholesaleLineIdsMap' => [],
            'botsList' => [],
            'receipts' => [],
            'receiptAggregates' => [],
            'broadcasts' => [],
            'broadcastQueueAggregates' => [],
            'navTabs' => [],
            'overview' => [],
            'monitorHosts' => [],
            'unitEconomics' => null,
            'panelEconomicsMap' => null,
            'resellerOverviewMetrics' => null,
        ];
    }
}
