<?php

namespace App\Modules\Core\Services\Portal;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Commerce\Mutations\CommerceMutations;
use App\Modules\Core\Mutations\CoreMutations;
use App\Modules\Reseller\Mutations\ResellerMutations;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Services\Commerce\ServiceTransferService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class PortalAdminService
{
    public function __construct(
        protected SettingsStore $settings,
        protected ResellerScopeService $scope,
        protected CommerceMutations $commerce,
        protected CoreMutations $core,
        protected ResellerMutations $reseller,
        protected ServiceTransferService $transfer,
        protected PortalPermissionService $permissions,
        protected PortalDashboardStatsService $stats,
        protected PortalBulkOpsService $bulkOps,
        protected PortalLinkService $portal,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(string $op, array $payload, SvpUser $admin): array
    {
        $op = strtolower(trim($op));
        if (! $this->permissions->mayCallOp($admin, $op)) {
            return svp_err('forbidden_perm');
        }
        if ($this->permissions->resellerActorId($admin) > 0 && $this->permissions->isSiteOnlyOp($op)) {
            return svp_err('forbidden');
        }

        return match ($op) {
            'stats' => $this->stats($admin, (int) ($payload['day'] ?? 0)),
            'membership_pending_page', 'membership_approved_page', 'membership_rejected_page' => $this->membershipPage(
                str_replace('membership_', '', str_replace('_page', '', $op)),
                (int) ($payload['offset'] ?? 0),
                $admin
            ),
            'membership_detail' => $this->membershipDetail($payload, $admin),
            'membership_approve' => $this->membershipAction((int) ($payload['user_id'] ?? 0), 'approved', $admin, 'pending'),
            'membership_reject' => $this->membershipAction((int) ($payload['user_id'] ?? 0), 'rejected', $admin, 'pending'),
            'membership_reopen' => $this->membershipAction((int) ($payload['user_id'] ?? 0), 'pending', $admin, 'rejected'),
            'create_service' => $this->createService($payload, $admin),
            'renew_service' => $this->renewService($payload, $admin),
            'add_volume' => $this->addVolume($payload, $admin),
            'service_transfer' => $this->serviceTransfer($payload, $admin),
            'receipts_page' => $this->receiptsPage((int) ($payload['offset'] ?? 0), $admin),
            'bulk_days' => $this->bulkDays($payload, $admin),
            'bulk_gb' => $this->bulkGb($payload, $admin),
            'save_crypto' => $this->saveCrypto($payload, $admin),
            'rotate_ipn_path' => $this->rotateIpnPath($admin),
            'referral_get' => $this->referralGet($admin),
            'referral_save' => $this->referralSave($payload, $admin),
            'discount_list' => $this->discountList($admin),
            'discount_save' => $this->discountSave($payload, $admin),
            'discount_delete' => $this->discountDelete((int) ($payload['discount_id'] ?? 0), $admin),
            default => svp_err('unknown_op'),
        };
    }

    protected function resellerActorId(SvpUser $admin): int
    {
        return $this->permissions->resellerActorId($admin);
    }

    protected function canAccessUser(SvpUser $admin, int $targetUid): bool
    {
        if ($targetUid < 1) {
            return false;
        }
        $rid = $this->resellerActorId($admin);
        if ($rid < 1) {
            return true;
        }

        return in_array($targetUid, $this->scope->moderatableUserIds($rid), true);
    }

    protected function stats(SvpUser $admin, int $day): array
    {
        $payload = $this->stats->buildPayload($day, $this->resellerActorId($admin));

        return svp_ok($payload);
    }

    protected function membershipPage(string $status, int $offset, SvpUser $admin): array
    {
        $limit = 5;
        $offset = max(0, $offset);
        $q = SvpUser::query()->where('status', $status)->orderByDesc('id');
        $rid = $this->resellerActorId($admin);
        if ($rid > 0) {
            $q->whereIn('id', $this->scope->moderatableUserIds($rid));
        }
        $total = (clone $q)->count();
        $rows = $q->offset($offset)->limit($limit)->get()->map(fn ($u) => [
            'id' => (int) $u->id,
            'label' => $this->userLabel($u),
            'status' => (string) $u->status,
            'created_at' => (string) $u->created_at,
        ])->all();

        return svp_ok([
            'status' => $status,
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'items' => $rows,
            'has_prev' => $offset > 0,
            'has_next' => ($offset + $limit) < $total,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    protected function membershipDetail(array $payload, SvpUser $admin): array
    {
        $userId = (int) ($payload['user_id'] ?? 0);
        if (! $this->canAccessUser($admin, $userId)) {
            return svp_err('forbidden_scope');
        }
        $u = SvpUser::query()->find($userId);
        if (! $u) {
            return svp_err('no_user');
        }

        $detail = $this->userPublicDetail($u);
        $tg = (int) ($u->tg_user_id ?? 0);
        if ($tg < 1 && (int) ($u->bale_user_id ?? 0) > 0) {
            $detail['bale_avatar_note'] = 'در بله تصویر پروفایل از طریق این پنل در دسترس نیست.';
        }
        if ($tg > 0) {
            $exp = (int) ($payload['svp_e'] ?? 0);
            $sig = (string) ($payload['svp_s'] ?? '');
            $detail['avatar_url'] = $this->portal->avatarUrl((int) $admin->id, $exp, $sig, $userId);
        }

        return svp_ok($detail);
    }

    protected function membershipAction(int $userId, string $status, SvpUser $admin, ?string $requiredStatus = null): array
    {
        if (! $this->canAccessUser($admin, $userId)) {
            return svp_err('forbidden_scope');
        }
        $u = SvpUser::query()->find($userId);
        if (! $u) {
            return svp_err('no_user');
        }
        if ($requiredStatus !== null && (string) $u->status !== $requiredStatus) {
            return svp_err('not_pending');
        }

        return app(\App\Modules\Core\Mutations\UserMutations::class)
            ->userStatus(['user_id' => $userId, 'status' => $status], null);
    }

    /** @param  array<string, mixed>  $payload */
    protected function createService(array $payload, SvpUser $admin): array
    {
        $tuid = (int) ($payload['target_uid'] ?? $payload['user_id'] ?? 0);
        if (! $this->canAccessUser($admin, $tuid)) {
            return svp_err('forbidden_scope');
        }
        $planId = (int) ($payload['plan_id'] ?? 0);
        if (! $this->mayUsePlan($admin, $planId)) {
            return svp_err('forbidden_plan');
        }

        $rid = $this->resellerActorId($admin);
        $result = app(\App\Services\Commerce\AdminUserOpsService::class)->adminCreateService(
            $tuid,
            $planId,
            ($payload['volume_gb'] ?? '') !== '' ? (int) $payload['volume_gb'] : null,
            (string) ($payload['mode'] ?? 'free'),
            $rid,
        );
        if (empty($result['ok'])) {
            return svp_err((string) ($result['reason'] ?? 'failed'));
        }

        return svp_ok($result);
    }

    protected function mayUsePlan(SvpUser $admin, int $planId): bool
    {
        if ($planId < 1) {
            return false;
        }
        $rid = $this->resellerActorId($admin);
        if ($rid < 1) {
            return true;
        }
        $plan = SvpPlan::query()->find($planId);
        if (! $plan) {
            return false;
        }

        return (int) ($plan->owner_svp_user_id ?? 0) === $rid || (int) ($plan->reseller_svp_user_id ?? 0) === $rid;
    }

    /** @param  array<string, mixed>  $payload */
    protected function renewService(array $payload, SvpUser $admin): array
    {
        $sid = (int) ($payload['service_id'] ?? 0);
        $svc = DB::table('svp_services')->where('id', $sid)->first();
        if (! $svc || ! $this->canAccessUser($admin, (int) $svc->user_id)) {
            return svp_err('forbidden_scope');
        }

        return $this->commerce->userRenewService([
            'service_id' => $sid,
            'mode' => (string) ($payload['mode'] ?? 'free'),
        ], null);
    }

    /** @param  array<string, mixed>  $payload */
    protected function addVolume(array $payload, SvpUser $admin): array
    {
        $sid = (int) ($payload['service_id'] ?? 0);
        $svc = DB::table('svp_services')->where('id', $sid)->first();
        if (! $svc || ! $this->canAccessUser($admin, (int) $svc->user_id)) {
            return svp_err('forbidden_scope');
        }

        return $this->commerce->userAddVolume([
            'service_id' => $sid,
            'extra_gb' => (int) ($payload['extra_gb'] ?? $payload['gb'] ?? 1),
            'mode' => (string) ($payload['mode'] ?? 'free'),
        ], null);
    }

    /** @param  array<string, mixed>  $payload */
    protected function serviceTransfer(array $payload, SvpUser $admin): array
    {
        $sid = (int) ($payload['service_id'] ?? 0);
        $tgt = trim((string) ($payload['target'] ?? ''));
        $svc = DB::table('svp_services')->where('id', $sid)->first();
        if (! $svc || ! $this->canAccessUser($admin, (int) $svc->user_id)) {
            return svp_err('forbidden_scope');
        }

        return $this->reseller->userServiceTransfer([
            'service_id' => $sid,
            'target' => $tgt,
        ], null);
    }

    protected function receiptsPage(int $offset, SvpUser $admin): array
    {
        $limit = 10;
        $offset = max(0, $offset);
        $q = DB::table('svp_receipts')->orderByDesc('id');
        $rid = $this->resellerActorId($admin);
        if ($rid > 0) {
            $q->whereIn('user_id', $this->scope->moderatableUserIds($rid));
        }
        $total = (clone $q)->count();
        $rows = $q->offset($offset)->limit($limit)->get();
        $items = [];
        foreach ($rows as $row) {
            $uid = (int) $row->user_id;
            $u = SvpUser::query()->find($uid);
            $items[] = [
                'id' => (int) $row->id,
                'user_id' => $uid,
                'user_label' => $u ? $this->userLabel($u) : '#'.$uid,
                'amount' => (float) ($row->amount ?? 0),
                'status' => (string) ($row->status ?? ''),
                'created_at' => (string) ($row->created_at ?? ''),
            ];
        }

        return svp_ok([
            'offset' => $offset,
            'limit' => $limit,
            'total' => $total,
            'items' => $items,
            'has_prev' => $offset > 0,
            'has_next' => ($offset + $limit) < $total,
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    protected function bulkDays(array $payload, SvpUser $admin): array
    {
        if ($this->resellerActorId($admin) > 0) {
            return svp_err('forbidden');
        }
        if (empty($payload['bulk_ack'])) {
            return svp_err('confirm_required');
        }
        $days = max(1, (int) ($payload['days'] ?? 1));

        return svp_ok($this->bulkOps->bulkExtendDays($days));
    }

    /** @param  array<string, mixed>  $payload */
    protected function bulkGb(array $payload, SvpUser $admin): array
    {
        if ($this->resellerActorId($admin) > 0) {
            return svp_err('forbidden');
        }
        if (empty($payload['bulk_ack'])) {
            return svp_err('confirm_required');
        }
        $gb = max(1, (int) ($payload['gb'] ?? 1));

        return svp_ok($this->bulkOps->bulkAddVolume($gb));
    }

    /** @param  array<string, mixed>  $payload */
    protected function saveCrypto(array $payload, SvpUser $admin): array
    {
        $this->settings->set('crypto_nowpayments_api_key', (string) ($payload['api_key'] ?? ''));
        $this->settings->set('crypto_nowpayments_ipn_secret', (string) ($payload['ipn_secret'] ?? ''));
        $this->settings->set('crypto_nowpayments_pay_currency', (string) ($payload['pay_currency'] ?? 'usdttrc20'));

        return svp_ok(['saved' => true]);
    }

    protected function rotateIpnPath(SvpUser $admin): array
    {
        $secret = bin2hex(random_bytes(16));
        $this->settings->set('crypto_ipn_path_secret', $secret);

        return svp_ok(['ipn_url' => url('/api/v1/crypto-ipn/'.$secret)]);
    }

    protected function referralGet(SvpUser $admin): array
    {
        return svp_ok([
            'referral_enabled' => (bool) $this->settings->get('referral_enabled', false),
            'referral_percent' => (float) $this->settings->get('referral_percent', 0),
            'referral_min_payout_base' => (float) $this->settings->get('referral_min_payout_base', 0),
            'referral_example_base_toman' => (float) $this->settings->get('referral_example_base_toman', 170000),
            'referral_example_invite_count' => (int) $this->settings->get('referral_example_invite_count', 10),
            'referral_require_approved_referrer' => (bool) $this->settings->get('referral_require_approved_referrer', true),
            'telegram_bot_username' => (string) $this->settings->get('telegram_bot_username', ''),
            'bale_bot_username' => (string) $this->settings->get('bale_bot_username', ''),
        ]);
    }

    /** @param  array<string, mixed>  $payload */
    protected function referralSave(array $payload, SvpUser $admin): array
    {
        $this->settings->set('referral_enabled', ! empty($payload['referral_enabled']));
        $this->settings->set('referral_percent', (float) ($payload['referral_percent'] ?? 0));
        $this->settings->set('referral_min_payout_base', (float) ($payload['referral_min_payout_base'] ?? 0));
        $this->settings->set('referral_example_base_toman', max(0.0, (float) str_replace(',', '.', (string) ($payload['referral_example_base_toman'] ?? '170000'))));
        $this->settings->set('referral_example_invite_count', max(1, (int) ($payload['referral_example_invite_count'] ?? 10)));
        $this->settings->set('referral_require_approved_referrer', ! empty($payload['referral_require_approved_referrer']));
        $this->settings->set('telegram_bot_username', (string) ($payload['telegram_bot_username'] ?? ''));
        $this->settings->set('bale_bot_username', (string) ($payload['bale_bot_username'] ?? ''));

        return svp_ok(['saved' => true]);
    }

    protected function discountList(SvpUser $admin): array
    {
        $rid = $this->resellerActorId($admin);
        $q = DB::table('svp_discount_codes')->orderBy('id');
        if ($rid > 0) {
            $q->where('owner_svp_user_id', $rid);
        }
        $items = $q->limit(200)->get()->map(fn ($r) => [
            'id' => (int) $r->id,
            'code' => (string) $r->code,
            'active' => (int) ($r->active ?? 0),
            'discount_type' => (string) ($r->discount_type ?? ''),
            'discount_value' => (float) ($r->discount_value ?? 0),
            'uses_count' => (int) ($r->uses_count ?? 0),
            'max_uses' => isset($r->max_uses) ? (int) $r->max_uses : null,
        ])->all();

        return svp_ok(['items' => $items]);
    }

    /** @param  array<string, mixed>  $payload */
    protected function discountSave(array $payload, SvpUser $admin): array
    {
        $planIds = [];
        $raw = trim((string) ($payload['discount_plan_ids'] ?? ''));
        if ($raw !== '') {
            foreach (preg_split('/\s*,\s*/', $raw) ?: [] as $part) {
                $n = (int) $part;
                if ($n > 0) {
                    $planIds[] = $n;
                }
            }
        }

        return $this->commerce->discountSave([
            'id' => (int) ($payload['discount_id'] ?? 0),
            'code' => (string) ($payload['discount_code'] ?? ''),
            'discount_type' => (string) ($payload['discount_type'] ?? 'percent'),
            'discount_value' => (float) ($payload['discount_value'] ?? 0),
            'active' => ! empty($payload['discount_active']),
            'max_uses' => ($payload['discount_max_uses'] ?? '') !== '' ? (int) $payload['discount_max_uses'] : null,
            'valid_from' => (string) ($payload['discount_valid_from'] ?? '') ?: null,
            'valid_until' => (string) ($payload['discount_valid_until'] ?? '') ?: null,
            'min_order_toman' => ($payload['discount_min_order'] ?? '') !== '' ? (float) $payload['discount_min_order'] : null,
            'max_order_toman' => ($payload['discount_max_order'] ?? '') !== '' ? (float) $payload['discount_max_order'] : null,
            'max_discount_toman' => ($payload['discount_max_discount'] ?? '') !== '' ? (float) $payload['discount_max_discount'] : null,
            'restricted_svp_user_id' => (int) ($payload['discount_restricted_user_id'] ?? 0),
            'allowed_plan_ids' => $planIds !== [] ? json_encode($planIds) : null,
            'allow_new_purchase' => ! empty($payload['discount_allow_new']),
            'allow_renew_same' => ! empty($payload['discount_allow_renew']),
            'allow_add_volume' => ! empty($payload['discount_allow_vol']),
            'allow_add_user_slots' => ! empty($payload['discount_allow_users']),
            'owner_svp_user_id' => $this->resellerActorId($admin),
        ], null);
    }

    protected function discountDelete(int $id, SvpUser $admin): array
    {
        if ($id < 1) {
            return svp_err('bad_id');
        }
        $row = DB::table('svp_discount_codes')->where('id', $id)->first();
        $rid = $this->resellerActorId($admin);
        if ($rid > 0 && (! $row || (int) ($row->owner_svp_user_id ?? 0) !== $rid)) {
            return svp_err('forbidden_scope');
        }
        DB::table('svp_discount_codes')->where('id', $id)->delete();

        return svp_ok(['deleted' => $id]);
    }

    /** @return array<string, mixed> */
    protected function userPublicDetail(SvpUser $u): array
    {
        return [
            'id' => (int) $u->id,
            'tg_user_id' => $u->tg_user_id ? (int) $u->tg_user_id : null,
            'bale_user_id' => $u->bale_user_id ? (int) $u->bale_user_id : null,
            'first_name' => (string) ($u->first_name ?? ''),
            'last_name' => (string) ($u->last_name ?? ''),
            'username' => (string) ($u->username ?? ''),
            'phone' => (string) ($u->phone ?? ''),
            'role' => (string) ($u->role ?? ''),
            'balance' => (string) ($u->balance ?? '0'),
            'status' => (string) ($u->status ?? ''),
            'approved_by' => $u->approved_by ?? null,
            'approved_at' => $u->approved_at ? (string) $u->approved_at : null,
            'admin_mode' => (int) ($u->admin_mode ?? 0),
            'invited_by' => $u->invited_by ? (int) $u->invited_by : null,
            'created_at' => (string) ($u->created_at ?? ''),
            'label' => $this->userLabel($u),
        ];
    }

    protected function userLabel(SvpUser $u): string
    {
        $label = trim((string) ($u->username ?: $u->first_name ?: ''));
        if ($label === '') {
            return '#'.$u->id;
        }

        return $label;
    }
}
