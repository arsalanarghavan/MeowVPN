<?php

namespace App\Services\AdminState;

use Illuminate\Http\Request;

class ListPagination
{
    /** @return array{page: int, per_page: int, offset: int} */
    public static function fromRequest(Request $request, string $paramPrefix, int $defaultPer, int $maxPer = 100): array
    {
        $page = max(1, (int) ($request->query("{$paramPrefix}_page") ?: 1));
        $perPage = max(1, min($maxPer, (int) ($request->query("{$paramPrefix}_per_page") ?: $defaultPer)));
        $offset = ($page - 1) * $perPage;

        return [
            'page' => $page,
            'per_page' => $perPage,
            'offset' => $offset,
        ];
    }

    /** @return array{page: int, perPage: int, total: int} */
    public static function meta(int $page, int $perPage, int $total): array
    {
        return [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
        ];
    }

    /** @return array<string, array{default: int, max: int, paginationKey: string}> */
    public static function listDefinitions(): array
    {
        return [
            'panels' => ['default' => 20, 'max' => 100, 'paginationKey' => 'panels'],
            'plans' => ['default' => 40, 'max' => 100, 'paginationKey' => 'plans'],
            'planCategories' => ['default' => 40, 'max' => 100, 'paginationKey' => 'planCategories'],
            'cards' => ['default' => 120, 'max' => 200, 'paginationKey' => 'cards'],
            'l2tp' => ['default' => 20, 'max' => 100, 'paginationKey' => 'l2tpServers'],
            'discounts' => ['default' => 120, 'max' => 200, 'paginationKey' => 'discountCodes'],
            'users' => ['default' => 50, 'max' => 200, 'paginationKey' => 'usersList'],
            'pendingUsers' => ['default' => 30, 'max' => 200, 'paginationKey' => 'pendingUsers'],
            'resellers' => ['default' => 30, 'max' => 200, 'paginationKey' => 'resellers'],
            'receipts' => ['default' => 40, 'max' => 200, 'paginationKey' => 'receipts'],
            'payments' => ['default' => 40, 'max' => 200, 'paginationKey' => 'payments'],
            'transactions' => ['default' => 40, 'max' => 200, 'paginationKey' => 'transactions'],
            'orders' => ['default' => 40, 'max' => 200, 'paginationKey' => 'orders'],
            'broadcasts' => ['default' => 20, 'max' => 100, 'paginationKey' => 'broadcasts'],
            'referralEvents' => ['default' => 20, 'max' => 100, 'paginationKey' => 'referralEvents'],
            'resellerReports' => ['default' => 25, 'max' => 100, 'paginationKey' => 'resellerReports'],
            'marketingOffers' => ['default' => 25, 'max' => 100, 'paginationKey' => 'marketingOffers'],
            'bots' => ['default' => 25, 'max' => 200, 'paginationKey' => 'botsList'],
            'mirrors' => ['default' => 25, 'max' => 200, 'paginationKey' => 'telegramMirrorsList'],
            'texts' => ['default' => 50, 'max' => 500, 'paginationKey' => 'texts'],
        ];
    }
}
