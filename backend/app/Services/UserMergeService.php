<?php

namespace App\Services;

use App\Models\SvpUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UserMergeService
{
    /** @return array{ok:bool, data?:array<string,mixed>, message?:string} */
    public function preview(int $keepId, int $dropId): array
    {
        if ($keepId < 1 || $dropId < 1 || $keepId === $dropId) {
            return svp_err('invalid_ids');
        }
        $keep = SvpUser::query()->find($keepId);
        $drop = SvpUser::query()->find($dropId);
        if (! $keep || ! $drop) {
            return svp_err('user_not_found');
        }

        $counts = $this->dropRelatedCounts($dropId);

        return svp_ok([
            'keep' => $this->userSummary($keep),
            'drop' => $this->userSummary($drop),
            'drop_related' => $counts,
        ]);
    }

    /** @return array{ok:bool, data?:array<string,mixed>, message?:string} */
    public function merge(int $keepId, int $dropId, bool $confirm, string $policy = 'strict'): array
    {
        if (! $confirm) {
            return svp_err('confirm_required');
        }
        if ($keepId < 1 || $dropId < 1 || $keepId === $dropId) {
            return svp_err('invalid_ids');
        }
        $keep = SvpUser::query()->find($keepId);
        $drop = SvpUser::query()->find($dropId);
        if (! $keep || ! $drop) {
            return svp_err('user_not_found');
        }
        $gate = $this->mergeAllowed($keep, $drop, $policy);
        if (! $gate['ok']) {
            return svp_err((string) ($gate['code'] ?? 'blocked'));
        }

        DB::beginTransaction();
        try {
            $upd = [];
            if (empty($keep->tg_user_id) && ! empty($drop->tg_user_id)) {
                $upd['tg_user_id'] = $drop->tg_user_id;
            }
            if (empty($keep->bale_user_id) && ! empty($drop->bale_user_id)) {
                $upd['bale_user_id'] = $drop->bale_user_id;
            }
            $upd['balance'] = (float) $keep->balance + (float) $drop->balance;
            SvpUser::query()->where('id', $keepId)->update($upd);

            $this->repointUserId($dropId, $keepId);
            SvpUser::query()->where('id', $dropId)->delete();
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::channel('svp')->error('merge_users failed', ['err' => $e->getMessage()]);

            return svp_err('merge_failed');
        }

        return svp_ok([
            'message' => 'merged',
            'drop_deleted' => SvpUser::query()->find($dropId) === null,
            'keep_id' => $keepId,
        ]);
    }

    /** @return array{ok:bool, code?:string} */
    public function mergeAllowed(SvpUser $keep, SvpUser $drop, string $policy = 'strict'): array
    {
        $kTg = (int) ($keep->tg_user_id ?? 0);
        $dTg = (int) ($drop->tg_user_id ?? 0);
        $kBl = (int) ($keep->bale_user_id ?? 0);
        $dBl = (int) ($drop->bale_user_id ?? 0);
        if ($kTg > 0 && $dTg > 0 && $kTg !== $dTg) {
            return ['ok' => false, 'code' => 'both_telegram'];
        }
        if ($kBl > 0 && $dBl > 0 && $kBl !== $dBl) {
            return ['ok' => false, 'code' => 'both_bale'];
        }
        if ($policy !== 'strict') {
            return ['ok' => true];
        }
        if ((string) $keep->role === 'reseller' || (string) $drop->role === 'reseller') {
            return ['ok' => false, 'code' => 'reseller'];
        }
        if ((string) $keep->status !== 'approved' || (string) $drop->status !== 'approved') {
            return ['ok' => false, 'code' => 'not_approved'];
        }

        return ['ok' => true];
    }

    /** @return array<string, int> */
    protected function dropRelatedCounts(int $dropId): array
    {
        $counts = [
            'services' => 0,
            'transactions' => 0,
            'receipts' => 0,
            'pending_approvals' => 0,
            'broadcast_queue' => 0,
            'sync_codes' => 0,
        ];
        if (Schema::hasTable('svp_services')) {
            $counts['services'] = (int) DB::table('svp_services')->where('user_id', $dropId)->whereNull('deleted_at')->count();
        }
        foreach (['svp_transactions' => 'transactions', 'svp_receipts' => 'receipts', 'svp_pending_approvals' => 'pending_approvals', 'svp_broadcast_queue' => 'broadcast_queue', 'svp_sync_codes' => 'sync_codes'] as $table => $key) {
            if (Schema::hasTable($table)) {
                $counts[$key] = (int) DB::table($table)->where('user_id', $dropId)->count();
            }
        }

        return $counts;
    }

    /** @return array<string, mixed> */
    protected function userSummary(SvpUser $user): array
    {
        return [
            'id' => (int) $user->id,
            'username' => (string) ($user->username ?? ''),
            'tg_user_id' => (int) ($user->tg_user_id ?? 0),
            'bale_user_id' => (int) ($user->bale_user_id ?? 0),
            'balance' => (float) ($user->balance ?? 0),
        ];
    }

    protected function repointUserId(int $fromId, int $toId): void
    {
        if (Schema::hasTable('svp_services')) {
            DB::table('svp_services')->where('user_id', $fromId)->whereNull('deleted_at')->update(['user_id' => $toId]);
        }
        foreach (['svp_transactions', 'svp_receipts', 'svp_pending_approvals', 'svp_broadcast_queue', 'svp_sync_codes'] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('user_id', $fromId)->update(['user_id' => $toId]);
            }
        }
        if (Schema::hasTable('svp_users')) {
            DB::table('svp_users')->where('invited_by', $fromId)->update(['invited_by' => $toId]);
        }
    }
}
