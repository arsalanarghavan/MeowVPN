<?php

namespace App\Services\Commerce;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReceiptRecoveryService
{
    public function __construct(
        protected ReceiptProcessorService $processor,
    ) {}

    public function runNotifyRecovery(): void
    {
        $cutoff = now()->subSeconds(120);
        $rows = DB::table('svp_receipts')
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(25)
            ->get();
        foreach ($rows as $rec) {
            if (trim((string) ($rec->tg_file_id ?? '')) === '' && trim((string) ($rec->bale_file_id ?? '')) === '') {
                continue;
            }
            // Notify recovery is handled by admin bot handlers; mark for reprocessing via log hook.
            if (Schema::hasTable('svp_logs')) {
                DB::table('svp_logs')->insert([
                    'level' => 'info',
                    'message' => 'receipt_notify_recovery_enqueued',
                    'context_json' => json_encode(['receipt_id' => (int) $rec->id]),
                    'created_at' => now(),
                ]);
            }
        }
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
