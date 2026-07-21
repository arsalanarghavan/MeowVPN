<?php

namespace App\Modules\Core\Mutations;

use App\Models\DashboardUser;
use App\Models\SvpUser;
use App\Modules\Core\Services\UsersBulkEnqueueService;
use App\Modules\Reseller\Services\ResellerClosureService;
use App\Services\SettingsStore;
use App\Services\UserMergeService;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class UserMutations
{
    public function __construct(
        protected UsersBulkEnqueueService $bulkEnqueue,
        protected UserMergeService $merge,
        protected SettingsStore $settings,
    ) {}
    /** @return array<string, array{0: class-string, 1: string}> */
    public function handlers(): array
    {
        return [
            'user_status' => [self::class, 'userStatus'],
            'user_balance_delta' => [self::class, 'userBalanceDelta'],
            'user_manual_create' => [self::class, 'userManualCreate'],
            'user_set_role' => [self::class, 'userSetRole'],
            'user_set_referrer' => [self::class, 'userSetReferrer'],
            'user_merge_preview' => [self::class, 'userMergePreview'],
            'user_merge' => [self::class, 'userMerge'],
            'users_bulk_wallet' => [self::class, 'usersBulkWallet'],
            'users_bulk_volume' => [self::class, 'usersBulkVolume'],
            'users_bulk_extend' => [self::class, 'usersBulkExtend'],
            'users_bulk_alerts' => [self::class, 'usersBulkAlerts'],
            'users_bulk_slots' => [self::class, 'usersBulkSlots'],
            'users_bulk_run_worker' => [self::class, 'usersBulkRunWorker'],
            'users_bulk_job_cancel' => [self::class, 'usersBulkJobCancel'],
            'users_bulk_job_resume' => [self::class, 'usersBulkJobResume'],
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function userStatus(array $payload, ?Authenticatable $actor): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $status = (string) ($payload['status'] ?? '');
        if ($userId < 1 || $status === '') {
            return svp_err('Invalid payload');
        }

        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return svp_err('User not found');
        }

        $user->status = $status;
        if ($status === 'approved') {
            $user->approved_at = now();
            $user->approved_by = $actor?->username ?? 'system';
        }
        $user->save();

        return svp_ok(['user_id' => $userId, 'status' => $status]);
    }

    /** @param  array<string, mixed>  $payload */
    public function userBalanceDelta(array $payload, ?Authenticatable $actor): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $delta = (float) ($payload['delta'] ?? 0);
        if ($userId < 1 || $delta == 0.0) {
            return svp_err('Invalid payload');
        }

        return DB::transaction(function () use ($userId, $delta, $actor) {
            $user = SvpUser::query()->lockForUpdate()->find($userId);
            if (! $user) {
                return svp_err('User not found');
            }

            $user->balance = bcadd((string) $user->balance, (string) $delta, 2);
            $user->save();

            DB::table('svp_transactions')->insert([
                'user_id' => $userId,
                'amount' => abs($delta),
                'type' => $delta > 0 ? 'admin_credit' : 'admin_debit',
                'status' => 'completed',
                'meta_json' => json_encode(['actor' => $actor?->username]),
                'created_at' => now(),
            ]);

            return svp_ok(['user_id' => $userId, 'balance' => $user->balance]);
        });
    }

    /** @param  array<string, mixed>  $payload */
    public function userManualCreate(array $payload, ?Authenticatable $actor): array
    {
        $invitedBy = (int) ($payload['invited_by'] ?? 0);
        $user = SvpUser::query()->create([
            'first_name' => (string) ($payload['first_name'] ?? ''),
            'last_name' => (string) ($payload['last_name'] ?? ''),
            'username' => (string) ($payload['username'] ?? ''),
            'phone' => (string) ($payload['phone'] ?? ''),
            'role' => (string) ($payload['role'] ?? 'user'),
            'status' => (string) ($payload['status'] ?? 'approved'),
            'balance' => (float) ($payload['balance'] ?? 0),
            'invited_by' => $invitedBy > 0 ? $invitedBy : null,
            'created_at' => now(),
        ]);

        if ($invitedBy > 0) {
            app(ResellerClosureService::class)->rebuildForUser((int) $user->id);
        }

        return svp_ok(['user_id' => $user->id]);
    }

    /** @param  array<string, mixed>  $payload */
    public function userSetRole(array $payload, ?Authenticatable $actor): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $role = (string) ($payload['role'] ?? '');
        if (in_array($role, ['admin', 'reseller'], true)
            && (! $actor instanceof DashboardUser || $actor->role !== 'admin')) {
            return svp_err('forbidden');
        }
        $user = SvpUser::query()->find($userId);
        if (! $user || $role === '') {
            return svp_err('Invalid payload');
        }
        $user->role = $role;
        $user->save();

        return svp_ok(['user_id' => $userId, 'role' => $role]);
    }

    /** @param  array<string, mixed>  $payload */
    public function userSetReferrer(array $payload, ?Authenticatable $actor): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        $referrerId = (int) ($payload['invited_by'] ?? 0);
        $user = SvpUser::query()->find($userId);
        if (! $user) {
            return svp_err('User not found');
        }

        $oldParent = (int) ($user->invited_by ?? 0);
        $newParent = $referrerId > 0 ? $referrerId : 0;

        $closure = app(ResellerClosureService::class);
        if ($newParent > 0 && $closure->wouldCreateCycle($userId, $newParent)) {
            return svp_err('referral_cycle');
        }

        $user->invited_by = $newParent > 0 ? $newParent : null;
        $user->save();

        $closure->onInvitedByChanged($userId, $oldParent, $newParent);

        return svp_ok(['user_id' => $userId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function userMergePreview(array $payload, ?Authenticatable $actor): array
    {
        $res = $this->merge->preview(
            (int) ($payload['keep_id'] ?? $payload['keep'] ?? $payload['target_id'] ?? 0),
            (int) ($payload['drop_id'] ?? $payload['drop'] ?? $payload['source_id'] ?? 0),
        );

        return ! empty($res['ok']) ? svp_ok(['data' => $res['data'] ?? []]) : $res;
    }

    /** @param  array<string, mixed>  $payload */
    public function userMerge(array $payload, ?Authenticatable $actor): array
    {
        $res = $this->merge->merge(
            (int) ($payload['keep_id'] ?? $payload['keep'] ?? $payload['target_id'] ?? 0),
            (int) ($payload['drop_id'] ?? $payload['drop'] ?? $payload['source_id'] ?? 0),
            ! empty($payload['confirm']),
        );

        return ! empty($res['ok']) ? svp_ok(['data' => $res['data'] ?? []]) : $res;
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkWallet(array $payload, ?Authenticatable $actor): array
    {
        return $this->enqueueBulkJob('wallet', $payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkVolume(array $payload, ?Authenticatable $actor): array
    {
        return $this->enqueueBulkJob('volume', $payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkExtend(array $payload, ?Authenticatable $actor): array
    {
        return $this->enqueueBulkJob('extend', $payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkAlerts(array $payload, ?Authenticatable $actor): array
    {
        return $this->enqueueBulkJob('alerts', $payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkSlots(array $payload, ?Authenticatable $actor): array
    {
        return $this->enqueueBulkJob('slots', $payload, $actor);
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkRunWorker(array $payload, ?Authenticatable $actor): array
    {
        \App\Modules\Core\Jobs\UsersBulkWorkerJob::dispatchSync();

        return svp_ok();
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkJobCancel(array $payload, ?Authenticatable $actor): array
    {
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($err = $this->assertBulkJobOwnership($jobId, $actor)) {
            return $err;
        }
        DB::table('svp_users_bulk_jobs')->where('id', $jobId)->update(['status' => 'cancelled']);

        return svp_ok(['job_id' => $jobId]);
    }

    /** @param  array<string, mixed>  $payload */
    public function usersBulkJobResume(array $payload, ?Authenticatable $actor): array
    {
        $jobId = (int) ($payload['job_id'] ?? 0);
        if ($err = $this->assertBulkJobOwnership($jobId, $actor)) {
            return $err;
        }
        DB::table('svp_users_bulk_jobs')->where('id', $jobId)->update(['status' => 'pending']);

        return svp_ok(['job_id' => $jobId]);
    }

    /** @return array{ok: false, message: string}|null */
    protected function assertBulkJobOwnership(int $jobId, ?Authenticatable $actor): ?array
    {
        if ($jobId < 1) {
            return svp_err('invalid');
        }
        if (! $actor instanceof DashboardUser || $actor->role !== 'reseller') {
            return null;
        }
        $job = DB::table('svp_users_bulk_jobs')->where('id', $jobId)->first();
        if (! $job) {
            return svp_err('not_found');
        }
        $actorId = (int) ($actor->svp_user_id ?? 0);
        if ($actorId < 1 || (int) ($job->created_by_svp_user_id ?? 0) !== $actorId) {
            return svp_err('forbidden');
        }

        return null;
    }

    /** @param  array<string, mixed>  $payload */
    protected function enqueueBulkJob(string $operation, array $payload, ?Authenticatable $actor): array
    {
        $notifyFields = $this->bulkNotifyFields($payload);
        $merged = array_merge($payload, $notifyFields, match ($operation) {
            'wallet' => [
                'delta' => (float) ($payload['delta'] ?? 0),
            ],
            'volume' => [
                'extra_gb' => max(1, (int) ($payload['extra_gb'] ?? $payload['volume_gb'] ?? 1)),
                'reduce' => ! empty($payload['reduce']),
            ],
            'extend' => [
                'days' => max(1, (int) ($payload['days'] ?? 1)),
                'reduce' => ! empty($payload['reduce']),
            ],
            'slots' => [
                'extra_users' => max(1, (int) ($payload['extra_users'] ?? 1)),
                'reduce' => ! empty($payload['reduce']),
            ],
            default => [],
        });

        return $this->bulkEnqueue->enqueueJob($operation, $merged, $actor);
    }

    /**
     * WP users_bulk_notify_fields: default notify = !suppress_bulk_user_notifications.
     *
     * @param  array<string, mixed>  $payload
     * @return array{notify: int, notify_message: string}
     */
    protected function bulkNotifyFields(array $payload): array
    {
        $notify = array_key_exists('notify', $payload)
            ? (bool) $payload['notify']
            : ! (bool) $this->settings->get('suppress_bulk_user_notifications', false);
        $msg = isset($payload['notify_message']) ? (string) $payload['notify_message'] : '';
        if (strlen($msg) > 3500) {
            $msg = function_exists('mb_substr') ? mb_substr($msg, 0, 3500) : substr($msg, 0, 3500);
        }

        return [
            'notify' => $notify ? 1 : 0,
            'notify_message' => $msg,
        ];
    }
}
