<?php

namespace App\Services\Commerce;

use App\Modules\Core\Bot\Services\ReceiptNotifyService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceiptRecoveryService
{
    public function __construct(
        protected ReceiptProcessorService $processor,
        protected ReceiptNotifyService $receiptNotify,
    ) {}

    public function runNotifyRecovery(): void
    {
        $this->receiptNotify->recoverPending(25);
    }

    public function runApproveRecovery(): void
    {
        $cutoff = now()->subSeconds(180);
        $rows = DB::table('svp_receipts')
            ->where('status', 'processing')
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(20)
            ->get();
        foreach ($rows as $rec) {
            $rid = (int) ($rec->id ?? 0);
            if ($rid < 1) {
                continue;
            }
            $tx = DB::table('svp_transactions')->where('id', (int) ($rec->transaction_id ?? 0))->first();
            if ($tx && (string) ($tx->status ?? '') === 'completed' && (int) ($tx->service_id ?? 0) > 0) {
                DB::table('svp_receipts')->where('id', $rid)->update(['status' => 'approved', 'decided_at' => now()]);

                continue;
            }
            DB::table('svp_receipts')->where('id', $rid)->where('status', 'processing')->update(['status' => 'pending']);
            $this->processor->approve($rid, 'recovery_cron');
        }

        // Parity with WP svp_deferred_receipt_provision_retry: re-fulfill after approve-time provision failures.
        $this->runProvisionFailedRetry();
    }

    /**
     * Retry fulfill for receipts left in provision_failed after a prior approve.
     */
    public function runProvisionFailedRetry(): void
    {
        $cutoff = now()->subSeconds(120);
        $rows = DB::table('svp_receipts')
            ->where('status', 'provision_failed')
            ->where(function ($q) use ($cutoff) {
                $q->where('decided_at', '<=', $cutoff)->orWhereNull('decided_at');
            })
            ->orderBy('id')
            ->limit(15)
            ->get();

        foreach ($rows as $rec) {
            $rid = (int) ($rec->id ?? 0);
            if ($rid < 1) {
                continue;
            }
            $this->processor->retryProvision($rid, 'provision_retry_cron');
        }
    }
}
