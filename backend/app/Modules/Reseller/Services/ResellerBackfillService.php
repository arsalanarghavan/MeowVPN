<?php

namespace App\Modules\Reseller\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ResellerBackfillService
{
    public function __construct(
        protected ResellerClosureService $closure,
    ) {}

    /** @return array{updated: int, scanned: int, last_id: int} */
    public function backfillBillingMetaBatch(int $limit = 500, int $afterId = 0): array
    {
        if (! Schema::hasTable('svp_transactions')) {
            return ['updated' => 0, 'scanned' => 0, 'last_id' => $afterId];
        }

        $lim = max(1, min(2000, $limit));
        $aid = max(0, $afterId);

        $rows = DB::table('svp_transactions')
            ->where('id', '>', $aid)
            ->where('status', 'approved')
            ->whereIn('type', ['purchase', 'topup'])
            ->orderBy('id')
            ->limit($lim)
            ->get();

        $updated = 0;
        $last = $aid;

        foreach ($rows as $row) {
            $last = (int) $row->id;
            if ($this->maybePersistBillingMetaOnTx($row)) {
                $updated++;
            }
        }

        return [
            'updated' => $updated,
            'scanned' => $rows->count(),
            'last_id' => $last,
        ];
    }

    /** @return array{updated: int, scanned: int, last_id: int} */
    public function backfillInvitedByBatch(int $limit = 500, int $afterId = 0): array
    {
        if (! Schema::hasTable('svp_users')) {
            return ['updated' => 0, 'scanned' => 0, 'last_id' => $afterId];
        }

        $lim = max(1, min(2000, $limit));
        $aid = max(0, $afterId);

        $users = DB::table('svp_users')
            ->where('id', '>', $aid)
            ->where('role', 'user')
            ->orderBy('id')
            ->limit($lim)
            ->get();

        $updated = 0;
        $last = $aid;

        foreach ($users as $user) {
            $last = (int) $user->id;
            $uid = (int) $user->id;

            $tx = DB::table('svp_transactions')
                ->where('user_id', $uid)
                ->where('status', 'approved')
                ->orderByDesc('id')
                ->first();

            if (! $tx) {
                continue;
            }

            $rid = $this->inferBillingResellerForTx($tx);
            if ($rid < 1) {
                continue;
            }

            $reseller = DB::table('svp_users')->where('id', $rid)->first();
            if (! $reseller || (string) $reseller->role !== 'reseller') {
                continue;
            }

            $cur = (int) ($user->invited_by ?? 0);
            if ($cur === $rid) {
                continue;
            }
            if ($cur > 0 && $cur !== $rid) {
                continue;
            }

            DB::table('svp_users')->where('id', $uid)->update(['invited_by' => $rid]);
            $this->closure->rebuildForUser($uid);
            $updated++;
        }

        return [
            'updated' => $updated,
            'scanned' => $users->count(),
            'last_id' => $last,
        ];
    }

    /** @return array<string, mixed> */
    public function runBatch(array $payload): array
    {
        $limit = max(1, min(2000, (int) ($payload['limit'] ?? 500)));
        $afterTx = max(0, (int) ($payload['after_tx_id'] ?? 0));
        $afterUser = max(0, (int) ($payload['after_user_id'] ?? 0));
        $rebuildClosure = ! empty($payload['rebuild_closure']);

        $billing = $this->backfillBillingMetaBatch($limit, $afterTx);
        $invited = $this->backfillInvitedByBatch($limit, $afterUser);

        if ($rebuildClosure) {
            $this->closure->rebuildAll();
        }

        return [
            'billing' => $billing,
            'invited_by' => $invited,
            'processed' => $billing['updated'] + $invited['updated'],
        ];
    }

    /** @param  object  $tx */
    protected function maybePersistBillingMetaOnTx(object $tx): bool
    {
        $meta = $this->parseTxMeta($tx);
        if (! empty($meta['billing_reseller_svp_id'])) {
            return false;
        }

        $rid = $this->inferBillingResellerForTx($tx);
        if ($rid < 1) {
            return false;
        }

        $meta = $this->mergeBillingIntoMeta($meta, $rid);
        DB::table('svp_transactions')->where('id', (int) $tx->id)->update([
            'meta_json' => json_encode($meta, JSON_UNESCAPED_UNICODE),
        ]);

        return true;
    }

    /** @param  object  $tx */
    protected function inferBillingResellerForTx(object $tx): int
    {
        $meta = $this->parseTxMeta($tx);
        if (! empty($meta['billing_reseller_svp_id'])) {
            return max(0, (int) $meta['billing_reseller_svp_id']);
        }
        if (! empty($meta['invoice_card_owner_scope_svp_id'])) {
            $rid = (int) $meta['invoice_card_owner_scope_svp_id'];

            return $rid > 0 ? $rid : 0;
        }

        $uid = (int) ($tx->user_id ?? 0);
        if ($uid < 1) {
            return 0;
        }

        $user = DB::table('svp_users')->where('id', $uid)->first();
        if ($user && ! empty($user->signup_reseller_svp_id)) {
            $sr = (int) $user->signup_reseller_svp_id;
            if ($sr > 0) {
                $reseller = DB::table('svp_users')->where('id', $sr)->first();
                if ($reseller && (string) $reseller->role === 'reseller') {
                    return $sr;
                }
            }
        }

        if ($user && ! empty($user->invited_by)) {
            $inv = (int) $user->invited_by;
            $inviter = DB::table('svp_users')->where('id', $inv)->first();
            if ($inviter && (string) $inviter->role === 'reseller') {
                return $inv;
            }
        }

        return 0;
    }

    /** @param  object  $tx  @return array<string, mixed> */
    protected function parseTxMeta(object $tx): array
    {
        $meta = json_decode((string) ($tx->meta_json ?? '{}'), true);

        return is_array($meta) ? $meta : [];
    }

    /** @param  array<string, mixed>  $meta  @return array<string, mixed> */
    protected function mergeBillingIntoMeta(array $meta, int $resellerId): array
    {
        if ($resellerId < 1) {
            return $meta;
        }
        if (empty($meta['billing_reseller_svp_id'])) {
            $meta['billing_reseller_svp_id'] = $resellerId;
        }
        if (empty($meta['invoice_card_owner_scope_svp_id'])) {
            $meta['invoice_card_owner_scope_svp_id'] = $resellerId;
        }

        return $meta;
    }
}
