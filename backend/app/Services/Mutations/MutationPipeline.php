<?php

namespace App\Services\Mutations;

use App\Models\DashboardUser;
use App\Services\AuditLogService;
use App\Services\ImpersonationService;
use App\Services\MutationRegistry;
use App\Support\Metrics\SvpMetrics;
use App\Services\UserActivityLogService;
use Illuminate\Contracts\Auth\Authenticatable;

class MutationPipeline
{
    public function __construct(
        protected MutationRegistry $registry,
        protected MutatePolicyService $policy,
        protected MutateScopeGuard $scopeGuard,
        protected AuditLogService $audit,
        protected UserActivityLogService $activity,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{result: array<string, mixed>, http_status: int}
     */
    public function dispatch(string $op, array $payload, ?Authenticatable $actor): array
    {
        $op = preg_replace('/[^a-z0-9_]/', '', strtolower($op)) ?? '';
        $op = $this->resolveOpAlias($op);

        if ($op === '') {
            return ['result' => ['ok' => false, 'message' => 'missing_op'], 'http_status' => 400];
        }

        if (! $actor instanceof DashboardUser) {
            return ['result' => ['ok' => false, 'message' => 'forbidden'], 'http_status' => 403];
        }

        if (! $this->registry->has($op)) {
            return ['result' => ['ok' => false, 'message' => 'unknown_op', 'code' => $op], 'http_status' => 422];
        }

        $impersonation = app(ImpersonationService::class);
        $isImpersonating = $actor->role === 'admin' && $impersonation->isActive();

        $policyErr = $isImpersonating
            ? $this->policy->assertImpersonatingAdminMayRun($op, $actor)
            : $this->policy->assertResellerMayRun($op, $actor);
        if ($policyErr !== null) {
            return ['result' => $policyErr, 'http_status' => 403];
        }

        if (! $isImpersonating) {
            $lifecycleErr = $this->policy->assertLifecycleFeature($op, $actor);
            if ($lifecycleErr !== null) {
                return ['result' => $lifecycleErr, 'http_status' => 403];
            }
        }

        if (str_starts_with($op, 'telegram_relay_') && ! svp_modules()->isEnabled('relay')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        if (str_starts_with($op, 'l2tp_') && ! svp_modules()->isEnabled('l2tp')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        if (str_starts_with($op, 'xray_') && ! svp_modules()->isEnabled('xray_core')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        if (str_starts_with($op, 'vpn_server_') && ! svp_modules()->isEnabled('xray_core')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        if (str_starts_with($op, 'tunnel_') && ! svp_modules()->isEnabled('tunnel')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        if ($this->isXuiPanelOp($op) && ! svp_modules()->isEnabled('xui_panel')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        if ($this->isMarketingOp($op) && ! svp_modules()->isEnabled('marketing')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        $resellerOps = str_starts_with($op, 'reseller_') || str_starts_with($op, 'bot_reseller_')
            || in_array($op, ['wholesale_line_save', 'wholesale_line_delete'], true);
        if ($resellerOps && ! svp_modules()->isEnabled('reseller')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        $botOps = ['bot_toggle_enabled', 'bot_toggle_platform_enabled', 'bot_test_telegram', 'bot_test_bale',
            'bot_diagnostics', 'bot_set_webhook', 'bot_delete_webhook', 'bot_admin_id_add', 'bot_admin_id_remove',
            'force_join_publish', 'telegram_proxy_test', 'texts_save', 'text_reset_one', 'texts_reset',
            'bot_ui_layout_save', 'bot_ui_layout_reset', 'bot_ui_group_create', 'bot_ui_group_delete',
            'telegram_mirror_save', 'telegram_mirror_delete', 'telegram_mirror_set_webhook',
            'telegram_mirror_delete_webhook', 'telegram_mirror_toggle', 'telegram_mirror_test',
            'telegram_mirror_diagnostics'];
        if (in_array($op, $botOps, true)
            && ! svp_modules()->isEnabled('telegram')
            && ! svp_modules()->isEnabled('bale')) {
            return ['result' => svp_err('module_disabled'), 'http_status' => 403];
        }

        $effectiveReseller = $actor->role === 'reseller' || $isImpersonating;
        $actorSvpId = $isImpersonating
            ? $impersonation->targetId()
            : (int) ($actor->svp_user_id ?? 0);

        $ctx = new MutateContext(
            op: $op,
            payload: $payload,
            actor: $actor,
            isReseller: $effectiveReseller,
            actorSvpUserId: $actorSvpId,
            resellerContextId: (int) ($payload['reseller_context_svp_user_id'] ?? 0),
        );

        $scopeErr = $this->scopeGuard->assertPayloadScope($op, $payload, $ctx);
        if ($scopeErr !== null) {
            return ['result' => $scopeErr, 'http_status' => 403];
        }

        $enriched = $this->scopeGuard->enrichPayload($payload, $ctx);
        $handler = $this->registry->all()[$op];

        if (is_array($handler)) {
            [$class, $method] = $handler;
            $result = app($class)->{$method}($enriched, $actor);
        } else {
            $result = $handler($enriched, $actor);
        }

        if (! is_array($result)) {
            $result = ['ok' => false, 'message' => 'invalid_handler_response'];
        }

        $this->audit->recordIfSensitive($op, $enriched, $result, $actor);

        $subjectUid = (int) ($enriched['user_id'] ?? $enriched['svp_user_id'] ?? 0);
        if (! empty($result['ok']) && $subjectUid > 0) {
            $this->activity->logUserEvent($subjectUid, $op, $enriched, $actor);
        }

        if (! empty($result['ok'])) {
            SvpMetrics::inc('mutate_op_total');
            SvpMetrics::inc('mutate_op_total:'.$op);
        }

        return [
            'result' => $result,
            'http_status' => $this->httpStatusFor($result),
        ];
    }

    /** @param  array<string, mixed>  $result */
    protected function httpStatusFor(array $result): int
    {
        if (! empty($result['ok'])) {
            return 200;
        }

        $msg = (string) ($result['message'] ?? '');
        if (in_array($msg, ['forbidden_op', 'forbidden_perm', 'forbidden_scope', 'forbidden'], true)) {
            return 403;
        }
        if (in_array($msg, ['invalid_reseller_context', 'missing_op', 'bad_request'], true)) {
            return 400;
        }
        if ($msg === 'module_disabled') {
            return 403;
        }

        return 422;
    }

    /** @var list<string> */
    private const XUI_PANEL_OPS = [
        'panel_xp', 'panel_test', 'panel_merge_preview', 'panel_merge_execute',
        'configs_panel_del_orphans', 'panels_repair_identities',
        'configs_bulk_reset_traffic', 'configs_reset_all_panel_traffic', 'configs_panel_del_depleted',
        'configs_client_fetch_ips', 'configs_client_clear_ips',
        'configs_client_set_inbounds', 'configs_clients_bulk_set_inbounds',
        'configs_inbound_patch', 'configs_delete_expired_older_than',
        'service_panel_sync', 'service_panel_refresh',
        'service_panel_delete_client', 'service_panel_transfer', 'service_apply_canonical_panel_identity',
        'user_create_service', 'user_renew_service', 'user_add_volume', 'user_reduce_volume',
        'user_add_days', 'user_reduce_days', 'user_service_reduce_slots', 'user_service_transfer',
        'user_service_toggle_enable', 'user_service_add_slots',
        'service_regen_key', 'service_regen_sub_id', 'service_set_limit_ip', 'service_alerts_patch',
        'configs_panel_client_patch', 'configs_clients_batch', 'configs_assign_plan',
        'configs_client_toggle_enable', 'configs_client_reset_traffic', 'configs_client_delete',
        'configs_delete_expired_linked', 'inbound_link', 'inbound_autolink',
        'purge_expired_run_cron', 'purge_expired_purge_ready', 'purge_expired_purge_one',
        'panel_economics_save', 'panel_economics_mark_paid', 'shared_economics_save',
        'unit_economics_save', 'unit_economics_config_save',
    ];

    /** @var list<string> */
    private const MARKETING_OPS = [
        'broadcast_send', 'broadcast_cancel', 'broadcast_run_worker',
        'marketing_rule_save', 'marketing_rule_delete', 'marketing_send_manual', 'marketing_run_rule_now',
        'marketing_lifecycle_confirm_defaults', 'marketing_preview_message',
    ];

    protected function isXuiPanelOp(string $op): bool
    {
        return in_array($op, self::XUI_PANEL_OPS, true)
            || str_starts_with($op, 'configs_')
            || str_starts_with($op, 'panel_economics_')
            || str_starts_with($op, 'purge_expired_');
    }

    protected function isMarketingOp(string $op): bool
    {
        return in_array($op, self::MARKETING_OPS, true) || str_starts_with($op, 'marketing_');
    }

    protected function resolveOpAlias(string $op): string
    {
        return $op;
    }
}
