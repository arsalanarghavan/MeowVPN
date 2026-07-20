<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpReceipt;
use App\Services\AdminState\AdminRowFormatter;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;
use App\Services\PaymentTransactionService;

class PaymentsLoader extends AbstractLoader
{
    public function __construct(protected PaymentTransactionService $payments) {}

    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsPayments();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        $paymentsView = $this->payments->sanitizePaymentsView($ctx->request);
        // Legacy `receipts` tab aliases to payments hub (receipts subview).
        if ($ctx->activeTab === 'receipts') {
            $paymentsView = 'receipts';
        }

        $receipts = [];
        $payments = [];
        $orders = [];
        $receiptAggregates = [];
        $paymentAggregates = [];
        $orderAggregates = [];

        $scopeEmpty = $ctx->isReseller && $ctx->moderatableUserIds === [];

        if ($ctx->activeTab === 'dashboard') {
            if ($this->tableExists('svp_receipts') && ! $scopeEmpty) {
                $q = $this->receiptsBaseQuery($ctx);
                $receipts = (clone $q)->limit(8)->get()
                    ->map(fn ($r) => AdminRowFormatter::formatReceipt($r))
                    ->values()
                    ->all();
                $receiptAggregates = $this->receiptAggregates($ctx, $q);
                $result->setTotal('receipts', (clone $q)->count());
            }
        } elseif (in_array($ctx->activeTab, ['payments', 'receipts'], true) && ! $scopeEmpty) {
            if ($paymentsView === 'receipts' && $this->tableExists('svp_receipts')) {
                $p = $this->payments->viewPagination($ctx->request, 'receipts', 40);
                $q = $this->receiptsBaseQuery($ctx);
                $this->applyReceiptFilters($q, $ctx);
                $total = (clone $q)->count();
                $result->setTotal('receipts', $total);
                $receipts = (clone $q)->offset($p['offset'])->limit($p['per_page'])->get()
                    ->map(fn ($r) => AdminRowFormatter::formatReceipt($r))
                    ->values()
                    ->all();
                $receiptAggregates = $this->receiptAggregates($ctx, $q);
            } elseif ($paymentsView === 'orders') {
                $p = $this->payments->viewPagination($ctx->request, 'orders', 40);
                $loaded = $this->payments->loadPaymentsView(
                    $ctx->request,
                    'orders',
                    $p['per_page'],
                    $p['offset'],
                    $ctx->moderatableUserIds,
                    $ctx->isReseller,
                );
                $orders = $loaded['rows'];
                $orderAggregates = $loaded['aggregates'];
                $result->setTotal('orders', $loaded['total']);
            } elseif ($paymentsView === 'transactions') {
                $p = $this->payments->viewPagination($ctx->request, 'transactions', 40);
                $loaded = $this->payments->loadPaymentsView(
                    $ctx->request,
                    'transactions',
                    $p['per_page'],
                    $p['offset'],
                    $ctx->moderatableUserIds,
                    $ctx->isReseller,
                );
                $payments = $loaded['rows'];
                $paymentAggregates = $loaded['aggregates'];
                $result->setTotal('transactions', $loaded['total']);
                $result->setTotal('payments', $loaded['total']);
            }
        }

        $result->merge([
            'paymentsView' => $paymentsView,
            'receipts' => $receipts,
            'receiptAggregates' => $receiptAggregates,
            'payments' => $payments,
            'paymentAggregates' => $paymentAggregates,
            'orders' => $orders,
            'orderAggregates' => $orderAggregates,
        ]);
    }

    /** @return \Illuminate\Database\Eloquent\Builder<\App\Models\SvpReceipt> */
    protected function receiptsBaseQuery(AdminStateContext $ctx)
    {
        $q = SvpReceipt::query()->orderByDesc('created_at')->orderByDesc('id');
        if ($ctx->moderatableUserIds !== []) {
            $q->whereIn('user_id', $ctx->moderatableUserIds);
        } elseif ($ctx->isReseller) {
            $q->whereRaw('1=0');
        }

        return $q;
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SvpReceipt>  $q */
    protected function applyReceiptFilters($q, AdminStateContext $ctx): void
    {
        $status = (string) ($ctx->request->query('receipts_status')
            ?: $ctx->request->query('payments_status', ''));
        if ($status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        $search = trim((string) ($ctx->request->query('receipts_q')
            ?: $ctx->request->query('payments_q', '')));
        if ($search !== '' && preg_match('/^\d+$/', $search)) {
            $q->where(function ($w) use ($search) {
                $w->where('id', (int) $search)->orWhere('user_id', (int) $search);
            });
        }
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\SvpReceipt>  $q
     * @return array<int, array{status: string, count: int, sumAmount: float}>|array{pending: int, approved: int}
     */
    protected function receiptAggregates(AdminStateContext $ctx, $q): array
    {
        if ($ctx->activeTab === 'dashboard') {
            return [
                'pending' => (clone $q)->where('status', 'pending')->count(),
                'approved' => SvpReceipt::query()
                    ->when($ctx->moderatableUserIds !== [], fn ($w) => $w->whereIn('user_id', $ctx->moderatableUserIds))
                    ->where('status', 'approved')
                    ->count(),
            ];
        }

        $rows = (clone $q)
            ->reorder()
            ->selectRaw('status, COUNT(*) as cnt, COALESCE(SUM(amount), 0) as sum_amount')
            ->groupBy('status')
            ->get();

        $out = [];
        foreach ($rows as $ar) {
            $out[] = [
                'status' => (string) ($ar->status ?? ''),
                'count' => (int) ($ar->cnt ?? 0),
                'sumAmount' => (float) ($ar->sum_amount ?? 0),
            ];
        }

        return $out;
    }
}
