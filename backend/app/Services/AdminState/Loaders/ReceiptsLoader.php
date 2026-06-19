<?php

namespace App\Services\AdminState\Loaders;

use App\Models\SvpReceipt;
use App\Services\AdminState\AdminRowFormatter;
use App\Services\AdminState\AdminStateContext;
use App\Services\AdminState\AdminStateResult;

class ReceiptsLoader extends AbstractLoader
{
    protected function shouldLoad(AdminStateContext $ctx): bool
    {
        return $ctx->needsReceipts();
    }

    protected function load(AdminStateContext $ctx, AdminStateResult $result): void
    {
        if (! $this->tableExists('svp_receipts')) {
            return;
        }

        $p = $ctx->page('receipts');
        $q = SvpReceipt::query()->orderByDesc('created_at')->orderByDesc('id');

        $status = (string) $ctx->request->query('receipts_status', '');
        if ($status !== '' && $status !== 'all') {
            $q->where('status', $status);
        }

        $search = trim((string) $ctx->request->query('receipts_q', ''));
        if ($search !== '' && preg_match('/^\d+$/', $search)) {
            $q->where(function ($w) use ($search) {
                $w->where('id', (int) $search)->orWhere('user_id', (int) $search);
            });
        }

        if ($ctx->moderatableUserIds !== []) {
            $q->whereIn('user_id', $ctx->moderatableUserIds);
        } elseif ($ctx->isReseller) {
            $q->whereRaw('1=0');
        }

        $total = (clone $q)->count();
        $result->setTotal('receipts', $total);

        $rows = (clone $q)->offset($p['offset'])->limit($p['per_page'])->get()
            ->map(fn ($r) => AdminRowFormatter::formatReceipt($r))
            ->values()
            ->all();

        $result->merge([
            'receipts' => $rows,
            'receiptAggregates' => [
                'pending' => (clone $q)->where('status', 'pending')->count(),
                'approved' => SvpReceipt::query()->when($ctx->moderatableUserIds !== [], fn ($w) => $w->whereIn('user_id', $ctx->moderatableUserIds))
                    ->where('status', 'approved')->count(),
            ],
        ]);
    }
}
