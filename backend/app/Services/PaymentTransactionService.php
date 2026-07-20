<?php

namespace App\Services;

use App\Models\SvpReceipt;
use App\Models\SvpUser;
use App\Services\AdminState\AdminRowFormatter;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unified payments hub query + formatting (WP Payment_Transaction + REST dashboard parity).
 */
class PaymentTransactionService
{
    /** @var list<string> */
    public const METHOD_KEYS = [
        'c2c', 'crypto', 'crypto_auto', 'crypto_tetra', 'rial_zarinpal', 'rial_aqayepardakht',
        'rial_zibal', 'bale_wallet', 'site_wallet', 'wallet_topup', 'admin', 'free', 'unknown',
    ];

    public function sanitizePaymentsView(Request $request): string
    {
        $v = preg_replace('/[^a-z]/', '', strtolower((string) $request->query('payments_view', ''))) ?? '';
        if (in_array($v, ['receipts', 'transactions', 'orders'], true)) {
            return $v;
        }

        return 'receipts';
    }

    /**
     * @return array{join_users: bool, join_receipts: bool, apply: callable(Builder): void, order_column: string, order_dir: string}
     */
    public function adminListQueryParts(Request $request, string $view = ''): array
    {
        $viewKey = $view !== '' ? $view : (string) $request->query('payments_view', '');
        $viewKey = preg_replace('/[^a-z]/', '', strtolower($viewKey)) ?? '';
        if (! in_array($viewKey, ['orders', 'transactions'], true)) {
            $viewKey = '';
        }

        $get = function (string $paymentsKey, string $receiptsKey) use ($request) {
            $v = $request->query($paymentsKey);
            if ($v !== null && $v !== '') {
                return $v;
            }

            return $request->query($receiptsKey, '');
        };

        $search = trim((string) $get('payments_q', 'receipts_q'));
        if (strlen($search) > 128) {
            $search = substr($search, 0, 128);
        }
        $joinUsers = $search !== '';

        $sort = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $get('payments_sort', 'receipts_sort'))) ?? '';
        $orderColumn = 'created_at';
        $orderDir = 'desc';
        if ($sort === 'created_asc') {
            $orderColumn = 'created_at';
            $orderDir = 'asc';
        } elseif ($sort === 'amount_desc') {
            $orderColumn = 'amount';
            $orderDir = 'desc';
        } elseif ($sort === 'amount_asc') {
            $orderColumn = 'amount';
            $orderDir = 'asc';
        } elseif ($sort === 'id_desc') {
            $orderColumn = 'id';
            $orderDir = 'desc';
        } elseif ($sort === 'id_asc') {
            $orderColumn = 'id';
            $orderDir = 'asc';
        }

        $apply = function (Builder $q) use ($viewKey, $get, $search): void {
            if ($viewKey === 'orders') {
                $q->whereIn('t.status', ['pending']);
            } elseif ($viewKey === 'transactions') {
                $q->whereIn('t.status', ['approved', 'rejected', 'cancelled']);
            }

            $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $get('payments_type', 'receipts_type'))) ?? '';
            if (in_array($type, ['purchase', 'topup', 'renew', 'volume', 'referral_commission'], true)) {
                $q->where('t.type', $type);
            }

            $method = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $get('payments_method', 'receipts_method'))) ?? '';
            if (in_array($method, self::METHOD_KEYS, true) && $method !== 'unknown') {
                $driver = DB::connection()->getDriverName();
                $methodExpr = $driver === 'sqlite'
                    ? "json_extract(t.meta_json, '$.payment_method')"
                    : "JSON_UNQUOTE(JSON_EXTRACT(t.meta_json, '$.payment_method'))";
                $q->where(function (Builder $w) use ($method, $methodExpr) {
                    $w->whereRaw("{$methodExpr} = ?", [$method])
                        ->orWhere(function (Builder $w2) use ($method, $methodExpr) {
                            $w2->whereRaw("{$methodExpr} IS NULL")
                                ->whereRaw('? = ?', [$method, 'c2c'])
                                ->whereNotNull('r.id');
                        });
                });
            }

            $status = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $get('payments_status', 'receipts_status'))) ?? '';
            if ($status !== 'all' && $status !== '' && $viewKey === '') {
                if ($status === 'pending') {
                    $q->whereIn('t.status', ['pending']);
                } elseif (in_array($status, ['approved', 'rejected', 'cancelled'], true)) {
                    $q->where('t.status', $status);
                } elseif ($status === 'processing') {
                    $q->where(function (Builder $w) {
                        $w->where('t.status', 'pending')
                            ->orWhereIn('r.status', ['pending', 'processing']);
                    });
                }
            }

            $df = trim((string) $get('payments_date_from', 'receipts_date_from'));
            if ($df !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $df)) {
                $q->where('t.created_at', '>=', substr($df, 0, 19));
            }
            $dt = trim((string) $get('payments_date_to', 'receipts_date_to'));
            if ($dt !== '' && preg_match('/^\d{4}-\d{2}-\d{2}/', $dt)) {
                $q->where('t.created_at', '<=', substr($dt, 0, 19));
            }

            $aminRaw = trim((string) $get('payments_amount_min', 'receipts_amount_min'));
            if ($aminRaw !== '' && is_numeric(str_replace(',', '.', $aminRaw))) {
                $q->where('t.amount', '>=', (float) str_replace(',', '.', $aminRaw));
            }
            $amaxRaw = trim((string) $get('payments_amount_max', 'receipts_amount_max'));
            if ($amaxRaw !== '' && is_numeric(str_replace(',', '.', $amaxRaw))) {
                $q->where('t.amount', '<=', (float) str_replace(',', '.', $amaxRaw));
            }

            if ($search !== '') {
                if (preg_match('/^\d+$/', $search)) {
                    $n = (int) $search;
                    $like = '%'.$search.'%';
                    $q->where(function (Builder $w) use ($n, $like) {
                        $w->where('t.id', $n)
                            ->orWhere('t.user_id', $n)
                            ->orWhere('r.id', $n)
                            ->orWhereRaw('CAST(t.amount AS CHAR) LIKE ?', [$like])
                            ->orWhere('u.username', 'like', $like)
                            ->orWhere('u.first_name', 'like', $like)
                            ->orWhere('u.last_name', 'like', $like);
                    });
                } else {
                    $like = '%'.$search.'%';
                    $q->where(function (Builder $w) use ($like) {
                        $w->where('u.username', 'like', $like)
                            ->orWhere('u.first_name', 'like', $like)
                            ->orWhere('u.last_name', 'like', $like)
                            ->orWhereRaw('CAST(t.amount AS CHAR) LIKE ?', [$like]);
                    });
                }
            }
        };

        return [
            'join_users' => $joinUsers,
            'join_receipts' => true,
            'apply' => $apply,
            'order_column' => $orderColumn,
            'order_dir' => $orderDir,
        ];
    }

    /**
     * @param  array<int, int>  $moderatableUserIds
     * @return Builder
     */
    public function baseTransactionQuery(array $filter, array $moderatableUserIds, bool $isReseller): Builder
    {
        $q = DB::table('svp_transactions as t');
        if (! empty($filter['join_receipts'])) {
            $q->leftJoin('svp_receipts as r', 'r.transaction_id', '=', 't.id');
        }
        if (! empty($filter['join_users'])) {
            $q->leftJoin('svp_users as u', 'u.id', '=', 't.user_id');
        }

        if ($moderatableUserIds !== []) {
            $q->whereIn('t.user_id', $moderatableUserIds);
        } elseif ($isReseller) {
            $q->whereRaw('1=0');
        }

        /** @var callable(Builder): void $apply */
        $apply = $filter['apply'];
        $apply($q);

        return $q;
    }

    /**
     * @param  array<int, int>  $moderatableUserIds
     * @return array{rows: array<int, array<string, mixed>>, total: int, aggregates: array<int, array{status: string, count: int, sumAmount: float}>}
     */
    public function loadPaymentsView(
        Request $request,
        string $view,
        int $limit,
        int $offset,
        array $moderatableUserIds,
        bool $isReseller,
    ): array {
        if (! Schema::hasTable('svp_transactions')) {
            return ['rows' => [], 'total' => 0, 'aggregates' => []];
        }

        $filter = $this->adminListQueryParts($request, $view);
        $base = $this->baseTransactionQuery($filter, $moderatableUserIds, $isReseller);

        $total = (int) (clone $base)->distinct()->count('t.id');

        $aggRows = (clone $base)
            ->selectRaw('t.status, COUNT(DISTINCT t.id) as cnt, COALESCE(SUM(t.amount), 0) as sum_amount')
            ->groupBy('t.status')
            ->get();

        $aggregates = [];
        foreach ($aggRows as $ar) {
            $aggregates[] = [
                'status' => (string) ($ar->status ?? ''),
                'count' => (int) ($ar->cnt ?? 0),
                'sumAmount' => (float) ($ar->sum_amount ?? 0),
            ];
        }

        $orderCol = in_array($filter['order_column'], ['created_at', 'amount', 'id'], true)
            ? $filter['order_column']
            : 'created_at';
        $orderDir = strtolower((string) $filter['order_dir']) === 'asc' ? 'asc' : 'desc';

        $rows = (clone $base)
            ->select('t.*')
            ->distinct()
            ->orderBy('t.'.$orderCol, $orderDir)
            ->when($orderCol !== 'id', fn ($q) => $q->orderBy('t.id', $orderDir))
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(fn ($row) => $this->formatPaymentForDashboard($row))
            ->filter()
            ->values()
            ->all();

        return ['rows' => $rows, 'total' => $total, 'aggregates' => $aggregates];
    }

    /** @return array<string, mixed>|null */
    public function formatPaymentForDashboard(mixed $row): ?array
    {
        if (! is_object($row) && ! is_array($row)) {
            return null;
        }
        $ra = AdminRowFormatter::rowArray($row);
        if ($ra === []) {
            return null;
        }

        $uid = (int) ($ra['user_id'] ?? 0);
        if ($uid > 0 && Schema::hasTable('svp_users')) {
            $user = SvpUser::query()->find($uid);
            if ($user) {
                $ra['user_label'] = trim(($user->username ?: '').' #'.$user->id) ?: ('#'.$user->id);
                $ra['user_name'] = trim((string) ($user->first_name ?? '').' '.(string) ($user->last_name ?? ''));
                $ra['username'] = (string) ($user->username ?? '');
                $ra['tg_user_id'] = (int) ($user->tg_user_id ?? 0);
                $ra['bale_user_id'] = (int) ($user->bale_user_id ?? 0);
            }
        }

        $receipt = null;
        $rid = 0;
        $txId = (int) ($ra['id'] ?? 0);
        if ($txId > 0 && Schema::hasTable('svp_receipts')) {
            $receipt = SvpReceipt::query()->where('transaction_id', $txId)->orderByDesc('id')->first();
            if ($receipt) {
                $rid = (int) $receipt->id;
            }
        }

        $method = $this->resolveMethod($row, $receipt);
        $labels = $this->dashboardProductLabels($row);
        $ra['payment_method'] = $method;
        $ra['payment_method_key'] = $this->methodLabelKey($method);
        $ra['service_label'] = (string) ($labels['service_label'] ?? '');
        $ra['panel_label'] = (string) ($labels['panel_label'] ?? '');
        $ra['plan_label'] = (string) ($labels['plan_label'] ?? '');
        $ra['product_summary'] = (string) ($labels['product_summary'] ?? '');
        $ra['selected_service'] = $ra['service_label'];
        $ra['transaction_type'] = (string) ($ra['type'] ?? '');
        $ra['transaction_amount'] = (float) ($ra['amount'] ?? 0);
        $ra['transaction_status'] = (string) ($ra['status'] ?? '');
        $ra['receipt_id'] = $rid > 0 ? $rid : null;
        $ra['receipt_status'] = $receipt ? (string) ($receipt->status ?? '') : '';
        $ra['can_review'] = $receipt && in_array((string) ($receipt->status ?? ''), ['pending', 'processing'], true);
        $ra['can_moderate_approved'] = $receipt && (string) ($receipt->status ?? '') === 'approved';
        if ($receipt) {
            $formatted = AdminRowFormatter::formatReceipt($receipt);
            $ra['imageUrl'] = (string) ($formatted['image_url'] ?? '');
            $ra['hasReceiptImage'] = $ra['imageUrl'] !== '';
        } else {
            $ra['imageUrl'] = '';
            $ra['hasReceiptImage'] = false;
        }

        return $ra;
    }

    public function resolveMethod(mixed $tx, mixed $receipt = null): string
    {
        if (! is_object($tx) && ! is_array($tx)) {
            return 'unknown';
        }
        $row = is_array($tx) ? (object) $tx : $tx;
        $meta = $row->meta_json ?? null;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        if (is_array($meta) && ! empty($meta['payment_method'])) {
            $m = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $meta['payment_method'])) ?? '';
            if (in_array($m, self::METHOD_KEYS, true)) {
                return $m;
            }
        }
        if ($receipt) {
            return 'c2c';
        }
        $txId = (int) ($row->id ?? 0);
        if ($txId > 0 && Schema::hasTable('svp_receipts')) {
            if (SvpReceipt::query()->where('transaction_id', $txId)->exists()) {
                return 'c2c';
            }
        }
        $type = preg_replace('/[^a-z0-9_]/', '', strtolower((string) ($row->type ?? ''))) ?? '';
        if ($type === 'topup') {
            return 'wallet_topup';
        }
        $amt = (float) ($row->amount ?? 0);
        if ((string) ($row->status ?? '') === 'approved' && abs($amt) < 0.00001) {
            return 'free';
        }

        return 'unknown';
    }

    public function methodLabelKey(string $method): string
    {
        $m = preg_replace('/[^a-z0-9_]/', '', strtolower($method)) ?? 'unknown';
        if (in_array($m, ['c2c', 'crypto', 'crypto_auto', 'crypto_tetra', 'rial_zarinpal', 'rial_aqayepardakht', 'rial_zibal', 'bale_wallet', 'site_wallet', 'wallet_topup', 'admin', 'free'], true)) {
            return $m;
        }

        return 'unknown';
    }

    /**
     * @return array{service_label: string, panel_label: string, plan_label: string, product_summary: string}
     */
    public function dashboardProductLabels(mixed $tx): array
    {
        $empty = [
            'service_label' => '',
            'panel_label' => '',
            'plan_label' => '',
            'product_summary' => '',
        ];
        if (! is_object($tx) && ! is_array($tx)) {
            return $empty;
        }
        $row = is_array($tx) ? (object) $tx : $tx;
        $meta = $row->meta_json ?? null;
        if (is_string($meta)) {
            $meta = json_decode($meta, true);
        }
        $meta = is_array($meta) ? $meta : [];

        $serviceLabel = '';
        $sid = (int) ($row->service_id ?? $meta['service_id'] ?? 0);
        if ($sid > 0 && Schema::hasTable('svp_services')) {
            $svc = DB::table('svp_services')->where('id', $sid)->first();
            if ($svc) {
                $serviceLabel = trim((string) ($svc->label ?? $svc->remark ?? ('#'.$sid)));
            }
        }

        $planLabel = '';
        $panelLabel = '';
        $pid = (int) ($meta['plan_id'] ?? 0);
        if ($pid > 0 && Schema::hasTable('svp_plans')) {
            $plan = DB::table('svp_plans')->where('id', $pid)->first();
            if ($plan) {
                $planLabel = trim((string) ($plan->name ?? ''));
                $panelId = max(1, (int) ($plan->panel_id ?? 1));
                if (Schema::hasTable('svp_panels')) {
                    $panel = DB::table('svp_panels')->where('id', $panelId)->first();
                    if ($panel) {
                        $panelLabel = trim((string) ($panel->label ?? ''));
                    }
                }
                if ($panelLabel === '') {
                    $panelLabel = 'Panel #'.$panelId;
                }
            }
        }
        if ($planLabel === '' && ! empty($meta['intent'])) {
            $planLabel = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $meta['intent'])) ?? '';
        }

        $productSummary = $serviceLabel !== '' ? $serviceLabel : $planLabel;

        return [
            'service_label' => $serviceLabel,
            'panel_label' => $panelLabel,
            'plan_label' => $planLabel,
            'product_summary' => $productSummary,
        ];
    }

    /**
     * Resolve pagination for a payments sub-view with payments_* / receipts_* fallbacks.
     *
     * @return array{page: int, per_page: int, offset: int}
     */
    public function viewPagination(Request $request, string $view, int $defaultPer = 40, int $maxPer = 100): array
    {
        $view = preg_replace('/[^a-z]/', '', strtolower($view)) ?? 'receipts';
        if (in_array($view, ['receipts', 'transactions', 'orders'], true)) {
            if ($request->query($view.'_page') !== null || $request->query($view.'_per_page') !== null) {
                return \App\Services\AdminState\ListPagination::fromRequest($request, $view, $defaultPer, $maxPer);
            }
        }
        if ($request->query('payments_page') !== null || $request->query('payments_per_page') !== null) {
            return \App\Services\AdminState\ListPagination::fromRequest($request, 'payments', $defaultPer, $maxPer);
        }

        return \App\Services\AdminState\ListPagination::fromRequest($request, 'receipts', $defaultPer, $maxPer);
    }
}
