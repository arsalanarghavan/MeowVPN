<?php

namespace App\Services\Mutations;

use App\Models\DashboardUser;
use App\Services\ImpersonationService;

class MutatePolicyService
{
    /** @var array<string, string> */
    protected static array $resellerMap = [
        'reseller_inbound_labels_save' => 'services.manage',
        'card_reorder' => 'plans.manage',
        'plan' => 'plans.manage',
        'plan_category' => 'plans.manage',
        'broadcast_send' => 'broadcast.send',
        'broadcast_cancel' => 'broadcast.send',
        'discount_redemptions' => 'plans.manage',
        'card_add' => 'plans.manage',
        'card_update' => 'plans.manage',
        'card_delete' => 'plans.manage',
        'reseller_payment_methods_save' => 'plans.manage',
        'receipt_action' => 'receipts.review',
        'receipt_set_status' => 'receipts.review',
        'receipt_update' => 'receipts.review',
        'membership' => 'users.manage',
        'user_status' => 'users.manage',
        'user_balance_delta' => 'users.manage',
        'user_create_service' => 'services.manage',
        'user_renew_service' => 'services.manage',
        'user_add_volume' => 'services.manage',
        'user_reduce_volume' => 'services.manage',
        'user_add_days' => 'services.manage',
        'user_reduce_days' => 'services.manage',
        'user_service_reduce_slots' => 'services.manage',
        'user_service_transfer' => 'services.manage',
        'user_service_toggle_enable' => 'services.manage',
        'service_delete' => 'services.manage',
        'user_admin_message' => 'users.manage',
        'service_alerts_patch' => 'services.manage',
        'service_set_note' => 'services.manage',
        'service_panel_sync' => 'services.manage',
        'service_regen_key' => 'services.manage',
        'service_regen_sub_id' => 'services.manage',
        'service_panel_refresh' => 'services.manage',
        'service_panel_delete_client' => 'services.manage',
        'user_service_add_slots' => 'services.manage',
        'service_set_limit_ip' => 'services.manage',
        'user_manual_create' => 'users.manage',
        'reseller_dashboard_provision' => 'users.manage',
        'bot_reseller_save' => 'services.manage',
        'bot_reseller_secret_rotate' => 'services.manage',
        'bot_reseller_toggle_enabled' => 'services.manage',
        'bot_test_telegram' => 'services.manage',
        'bot_test_bale' => 'services.manage',
        'bot_diagnostics' => 'services.manage',
        'reseller_bot_webhook_set' => 'services.manage',
        'reseller_bot_webhook_delete' => 'services.manage',
        'reseller_bot_tokens_save' => 'services.manage',
        'telegram_relay_set_webhook_reseller' => 'services.manage',
        'bot_admin_id_add' => 'services.manage',
        'bot_admin_id_remove' => 'services.manage',
        'reseller_panel_prices_save' => 'users.manage',
        'users_bulk_wallet' => 'users.bulk',
        'users_bulk_volume' => 'users.bulk',
        'users_bulk_extend' => 'users.bulk',
        'users_bulk_alerts' => 'users.bulk',
        'users_bulk_slots' => 'users.bulk',
        'users_bulk_job_cancel' => 'users.bulk',
        'users_bulk_job_resume' => 'users.bulk',
        'reseller_wallet_topup_checkout' => 'plans.manage',
    ];

    /** @var list<string> */
    protected static array $lifecycleOps = [
        'marketing_rule_save',
        'marketing_rule_delete',
        'marketing_send_manual',
        'marketing_run_rule_now',
    ];

    public function requiredResellerPermission(string $op): ?string
    {
        $op = $this->sanitizeOp($op);

        return self::$resellerMap[$op] ?? null;
    }

    public function isAdminOnly(string $op): bool
    {
        return $this->requiredResellerPermission($op) === null;
    }

    /**
     * @return array{ok: false, message: string}|null
     */
    /**
     * When admin impersonates a reseller, restrict mutate to reseller-allowed ops + perms.
     *
     * @return array{ok: false, message: string}|null
     */
    public function assertImpersonatingAdminMayRun(string $op, DashboardUser $actor): ?array
    {
        if ($actor->role !== 'admin' || ! app(ImpersonationService::class)->isActive()) {
            return null;
        }

        $perm = $this->requiredResellerPermission($op);
        if ($perm === null) {
            return ['ok' => false, 'message' => 'forbidden_op'];
        }

        $targetId = app(ImpersonationService::class)->targetId();
        $dash = DashboardUser::query()
            ->where('role', 'reseller')
            ->where('svp_user_id', $targetId)
            ->first();
        if (! $dash) {
            return ['ok' => false, 'message' => 'forbidden'];
        }

        $perms = is_array($dash->permissions_json) ? $dash->permissions_json : [];
        if ($perm !== '' && empty($perms[$perm])) {
            return ['ok' => false, 'message' => 'forbidden_perm'];
        }

        if (in_array($this->sanitizeOp($op), self::$lifecycleOps, true)) {
            return ['ok' => false, 'message' => 'forbidden_op'];
        }

        return null;
    }

    public function assertResellerMayRun(string $op, DashboardUser $actor): ?array
    {
        if ($actor->role !== 'reseller') {
            return null;
        }

        $perm = $this->requiredResellerPermission($op);
        if ($perm === null) {
            return ['ok' => false, 'message' => 'forbidden_op'];
        }

        $perms = is_array($actor->permissions_json) ? $actor->permissions_json : [];
        if ($perm !== '' && empty($perms[$perm])) {
            return ['ok' => false, 'message' => 'forbidden_perm'];
        }

        return null;
    }

    /**
     * @return array{ok: false, message: string}|null
     */
    public function assertLifecycleFeature(string $op, DashboardUser $actor): ?array
    {
        $op = $this->sanitizeOp($op);
        if (! in_array($op, self::$lifecycleOps, true)) {
            return null;
        }
        if ($actor->role === 'admin') {
            return null;
        }

        return ['ok' => false, 'message' => 'forbidden_op'];
    }

    protected function sanitizeOp(string $op): string
    {
        return preg_replace('/[^a-z0-9_]/', '', strtolower($op)) ?? '';
    }
}
