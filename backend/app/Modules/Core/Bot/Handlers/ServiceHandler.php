<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Jobs\DeferredC2cInvoiceJob;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotCheckoutUiService;
use App\Modules\Core\Bot\Services\BotCommerceCheckoutService;
use App\Modules\Core\Bot\Services\BotConfigDeliveryService;
use App\Modules\Core\Bot\Services\BotDeferredCacheService;
use App\Modules\Core\Bot\Services\BotManagedUserContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotServiceCheckoutService;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\BotSvcPanelDeliveryService;
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Bot\Services\UiLayoutService;
use App\Modules\Core\Services\Portal\PortalConfigUriCollector;
use App\Modules\L2tp\Services\L2tpProvisionerService;
use App\Modules\PasarGuard\Services\PanelClientFactory;
use App\Services\ServiceAlertsHelper;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ServiceHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
        protected BotStateService $state,
        protected BotConfigDeliveryService $delivery,
        protected BotServiceCheckoutService $serviceCheckout,
        protected BotCommerceCheckoutService $commerce,
        protected BotCheckoutUiService $checkoutUi,
        protected AdminGuard $adminGuard,
        protected BotManagedUserContext $managed,
        protected BotAdminMutateService $mutate,
        protected BotDeferredCacheService $deferredCache,
        protected BotSvcPanelDeliveryService $svcPanelDelivery,
        protected UiLayoutService $uiLayout,
        protected PortalConfigUriCollector $uriCollector,
        protected ServiceAlertsHelper $alertsHelper,
        protected L2tpProvisionerService $l2tpProvisioner,
    ) {}

    public function listServices(BotContext $ctx, SvpUser $user, int $chatId, int $offset = 0): void
    {
        $off = max(0, $offset);
        $q = SvpService::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderByDesc('id');
        $total = (int) $q->count();
        $services = $q->offset($off)->limit(10)->get();

        if ($services->isEmpty()) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.none', $user));

            return;
        }

        $rows = [];
        $i = $off;
        foreach ($services as $svc) {
            $i++;
            $label = ServiceNaming::formatServiceDisplayLabel($svc, $i);
            $rows[] = [['text' => $label, 'callback_data' => 'svc:m:'.$svc->id]];
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'svc:lp:'.max(0, $off - 10)];
        }
        if ($total > $off + 10) {
            $nav[] = ['text' => '▶', 'callback_data' => 'svc:lp:'.($off + 10)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.list', $user), [
            'reply_markup' => $this->keyboards->inline($rows),
        ]);
    }

    /** @param  array<int, string>  $parts */
    public function handlePaymentParts(BotContext $ctx, SvpUser $user, array $parts, int $chatId, string $cbId = ''): bool
    {
        $action = (string) ($parts[1] ?? '');
        if (! in_array($action, ['pm', 'sw', 'swy', 'swn', 'bw', 'cr', 'dc', 'dd', 'x'], true)) {
            return false;
        }

        if ($action === 'x') {
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return true;
        }

        if ($action === 'dc' && isset($parts[2])) {
            $this->state->set($user, 'service_discount', ['transaction_id' => (int) $parts[2]]);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.prompt_discount', $user, 'Send discount code'));

            return true;
        }

        if ($action === 'dd' && isset($parts[2])) {
            $txId = (int) $parts[2];
            $result = $this->commerce->clearDiscount($txId);
            if (! empty($result['ok'])) {
                $this->refreshServiceCheckout($ctx, $user, $chatId, $txId);
            }

            return true;
        }

        if ($action === 'pm' && isset($parts[2], $parts[3])) {
            $txId = (int) $parts[2];
            $cardId = (int) $parts[3];
            $this->deferredCache->putC2c($user->id, $chatId, $ctx->platform, $txId, $cardId, $cbId !== '' ? $cbId : null);
            DeferredC2cInvoiceJob::dispatch($user->id, $chatId, $ctx->platform, $txId, $cardId, $cbId !== '' ? $cbId : null);

            return true;
        }

        if ($action === 'sw' && isset($parts[2])) {
            $this->promptWalletPartial($ctx, $user, $chatId, (int) $parts[2]);

            return true;
        }

        if ($action === 'swy' && isset($parts[2])) {
            $result = $this->commerce->payWithWallet($user, (int) $parts[2], true);
            $this->handleWalletResult($ctx, $user, $chatId, $result);

            return true;
        }

        if ($action === 'swn') {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return true;
        }

        if ($action === 'bw' && isset($parts[2])) {
            $result = $this->commerce->sendBaleInvoice($ctx, $user, $chatId, (int) $parts[2], $this->texts->getForUser('msg.service.bale_invoice_title', $user, 'Service payment'));
            if (empty($result['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.bale_invoice_failed', $user, 'Bale payment failed'));
            }

            return true;
        }

        if ($action === 'cr' && isset($parts[2])) {
            $link = $this->commerce->createCryptoPaymentLink($user, (int) $parts[2]);
            $this->runtime->sendMessage($ctx, $chatId, ! empty($link['ok'])
                ? ($this->texts->getForUser('msg.buy.crypto_link', $user, 'Pay:').' '.(string) ($link['link'] ?? ''))
                : $this->texts->getForUser('msg.common.failed', $user, 'Failed'));

            return true;
        }

        return false;
    }

    /** @param  array<string, mixed>  $payload */
    public function handleCallback(BotContext $ctx, SvpUser $user, array $payload): void
    {
        $action = (string) ($payload['action'] ?? '');
        $svcId = (int) ($payload['svc_id'] ?? 0);
        $chatId = (int) ($payload['chat_id'] ?? 0);
        $fromId = (int) ($payload['from_id'] ?? 0);

        if (in_array($action, ['pm', 'sw', 'swy', 'swn', 'bw', 'cr', 'dc', 'dd', 'x'], true)) {
            $parts = ['svc', $action, (string) $svcId];
            if (isset($payload['extra'])) {
                $parts = array_merge($parts, (array) $payload['extra']);
            }
            $this->handlePaymentParts($ctx, $user, $parts, $chatId, (string) ($payload['cb_id'] ?? ''));

            return;
        }

        $svc = $this->resolveService($ctx, $user, $svcId, $fromId);
        if (! $svc) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.forbidden', $user, 'Access denied'));

            return;
        }

        $cbId = (string) ($payload['cb_id'] ?? '');
        $msgId = (int) ($payload['msg_id'] ?? 0);
        $parts = (array) ($payload['parts'] ?? []);
        $isL2tp = (string) ($svc->service_type ?? '') === 'l2tp';

        // WP L2TP: p/l/q show credentials; k rotates password; u/ip N/A (f = FAQ).
        if ($isL2tp && in_array($action, ['p', 'l', 'q', 'cfg'], true)) {
            $this->delivery->enqueue($ctx, $user, $chatId, (int) $svc->id, 'config', $cbId !== '' ? $cbId : null);

            return;
        }
        if ($isL2tp && $action === 'k') {
            $this->handleL2tpRotatePassword($ctx, $user, $chatId, $svc);

            return;
        }
        if ($isL2tp && in_array($action, ['u', 'ip'], true)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.l2tp_option_na', $user, 'Not available for L2TP'));

            return;
        }

        if (preg_match('/^a[0-8]$/', $action)) {
            $this->handleAlertSubCallback($ctx, $user, $chatId, $msgId, $svc, $action);

            return;
        }

        match ($action) {
            'lp' => $this->listServices($ctx, $user, $chatId, $svcId),
            // WP: card = svc:m (not svc:v)
            'm' => $this->sendServiceCard($ctx, $user, $chatId, $svc, true, $msgId),
            // WP: svc:us = usage panel
            'us' => $this->showUsagePanel($ctx, $user, $chatId, $svc, $msgId),
            // WP schedule_svc_panel_full_delivery
            'p' => $this->svcPanelDelivery->schedule($ctx, $user, $chatId, $msgId, $svc, 'p', $cbId !== '' ? $cbId : null),
            'l' => $this->svcPanelDelivery->schedule($ctx, $user, $chatId, 0, $svc, 'l', $cbId !== '' ? $cbId : null),
            'q', 'cfg' => $this->svcPanelDelivery->schedule($ctx, $user, $chatId, 0, $svc, 'q', $cbId !== '' ? $cbId : null),
            'r' => $this->doRenew($ctx, $user, $chatId, $svcId),
            // WP: svc:v = add volume; keep av as legacy alias
            'v', 'av' => $this->dispatchAddVolume($ctx, $user, $chatId, $svc, $svcId),
            // WP: svc:sl = add slots
            'sl' => $this->dispatchAddSlots($ctx, $user, $chatId, $svc, $svcId),
            'al' => $this->alertsRenderMainPanel($ctx, $user, $chatId, $msgId, $svc),
            'nt', 'n' => $this->startNote($ctx, $user, $chatId, $svcId),
            'rn' => $this->startRename($ctx, $user, $chatId, $svcId),
            'del' => $this->confirmDelete($ctx, $user, $chatId, $svcId),
            'dely' => $this->softDeleteService($ctx, $user, $chatId, $svcId),
            // WP: svc:tx = transfer code; xf = legacy direct-target prompt
            'tx' => $this->createTransferCode($ctx, $user, $chatId, $svc),
            'xf' => $this->startTransfer($ctx, $user, $chatId, $svcId),
            'li' => $this->startLimitIp($ctx, $user, $chatId, $svcId),
            'ip' => $this->showActiveConnections($ctx, $user, $chatId, $svc),
            'sync', 'u' => $this->refreshServers($ctx, $user, $chatId, $svc),
            // WP regen: svc:k / svc:rs (rk legacy alias)
            'k', 'rk' => $this->handleRegenKey($ctx, $user, $chatId, $svc, $fromId),
            'rs' => $this->handleRegenSub($ctx, $user, $chatId, $svc, $fromId),
            'ar' => $this->toggleAutorenew($ctx, $user, $chatId, $svc),
            'pe' => $this->togglePanelEnable($ctx, $user, $chatId, $msgId, $svc, $fromId),
            'f' => $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser(
                $isL2tp ? 'faq.l2tp' : 'faq.connection',
                $user,
                'FAQ'
            )),
            'su' => $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.support_contact_admin', $user, 'Contact support')),
            'b' => $this->listServices($ctx, $user, $chatId, 0),
            'pqt' => $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('faq.purchase.connection', $user, 'Connection guide')),
            'pqf' => $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('faq.purchase.faq', $user, 'Purchase FAQ')),
            'pqs' => $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.support.intro', $user, 'Support')),
            'ca' => $this->handleConfigCopyAll($ctx, $user, $chatId, $svc, $cbId),
            'w' => $this->handleConfigWire($ctx, $user, $chatId, $svc, (int) ($parts[3] ?? -1), $cbId),
            'alt' => $this->toggleAlert($ctx, $user, $chatId, $svc, $parts),
            'sub' => $this->svcPanelDelivery->schedule($ctx, $user, $chatId, 0, $svc, 'sub', $cbId !== '' ? $cbId : null),
            default => $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.unknown_action', $user, 'Unknown action')),
        };
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $svcId = (int) ($data['service_id'] ?? 0);
        $trimmed = trim($text);

        if ($state === 'service_discount') {
            $txId = (int) ($data['transaction_id'] ?? 0);
            $result = $this->commerce->applyDiscountCode($txId, $trimmed, $user->id);
            $this->state->clear($user);
            if (! empty($result['ok'])) {
                $this->refreshServiceCheckout($ctx, $user, $chatId, $txId);
            } else {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.discount_invalid', $user, 'Invalid code'));
            }

            return;
        }

        if ($state === 'service_checkout') {
            $txId = (int) ($data['transaction_id'] ?? 0);
            if ($txId > 0) {
                $this->refreshServiceCheckout($ctx, $user, $chatId, $txId);
            }

            return;
        }

        if ($state === 'service_addvol' && is_numeric($trimmed) && $svcId > 0) {
            $gb = max(1, (int) $trimmed);
            $result = $this->serviceCheckout->beginAddVolume($user, $ctx, $svcId, $gb);
            if (! empty($result['transaction_id']) && ! empty($data['marketing_code_id'])) {
                $code = DB::table('svp_discount_codes')->where('id', (int) $data['marketing_code_id'])->value('code');
                if ($code) {
                    $txId = (int) $result['transaction_id'];
                    $apply = $this->commerce->applyDiscountCode($txId, (string) $code, (int) $user->id);
                    if (! empty($apply['ok']) && ! empty($data['marketing_offer_id'])) {
                        $meta = json_decode((string) (DB::table('svp_transactions')->where('id', $txId)->value('meta_json') ?? '{}'), true);
                        $meta = is_array($meta) ? $meta : [];
                        $meta['marketing_offer_id'] = (int) $data['marketing_offer_id'];
                        DB::table('svp_transactions')->where('id', $txId)->update(['meta_json' => json_encode($meta)]);
                    }
                }
            }
            $this->state->clear($user);
            $this->handleCheckoutResult($ctx, $user, $chatId, $result, $svcId, 'Add volume');

            return;
        }

        if ($state === 'service_addusers' && is_numeric($trimmed) && $svcId > 0) {
            $slots = max(1, (int) $trimmed);
            $result = $this->serviceCheckout->beginAddSlots($user, $ctx, $svcId, $slots);
            $this->state->clear($user);
            $this->handleCheckoutResult($ctx, $user, $chatId, $result, $svcId, 'Add slots');

            return;
        }

        if ($state === 'service_transfer' && $svcId > 0) {
            $targetId = (int) preg_replace('/\D/', '', $trimmed);
            if ($targetId < 1) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.transfer_invalid', $user, 'Invalid user id'));

                return;
            }
            $result = $this->mutate->applyForUser($user, 'user_service_transfer', [
                'service_id' => $svcId,
                'target' => $targetId,
            ]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'service_limit_ip' && is_numeric($trimmed) && $svcId > 0) {
            $result = $this->mutate->applyForUser($user, 'service_set_limit_ip', [
                'service_id' => $svcId,
                'limit_ip' => max(0, (int) $trimmed),
            ]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'service_alerts_pct' && is_numeric($trimmed) && $svcId > 0) {
            $this->state->set($user, 'service_alerts_exp', [
                'service_id' => $svcId,
                'traffic_pct' => min(100, max(1, (int) $trimmed)),
            ]);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_alerts_exp', $user, 'Send expiry alert days before'));

            return;
        }

        if ($state === 'service_alerts_exp' && is_numeric($trimmed) && $svcId > 0) {
            $this->state->set($user, 'service_alerts_users', [
                'service_id' => $svcId,
                'traffic_pct' => (int) ($data['traffic_pct'] ?? 80),
                'expiry_days' => max(1, (int) $trimmed),
            ]);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_alerts_users', $user, 'Send users alert % (50-100)'));

            return;
        }

        if ($state === 'service_alerts_users' && is_numeric($trimmed) && $svcId > 0) {
            $pct = min(100, max(50, (int) $trimmed));
            $result = $this->mutate->applyForUser($user, 'service_alerts_patch', [
                'service_id' => $svcId,
                'alerts' => [
                    'alerts_enabled' => true,
                    'alerts_volume' => true,
                    'alerts_expiry' => true,
                    'alerts_users' => true,
                    'alert_low_pct' => (int) ($data['traffic_pct'] ?? 80),
                    'alert_expiry_days' => (string) ($data['expiry_days'] ?? 3),
                    'alert_ip_fill_pct' => $pct,
                ],
            ]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'service_note' && $svcId > 0) {
            DB::table('svp_services')->where('id', $svcId)->update(['service_note' => $trimmed]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.note_ok', $user, 'Note saved'));

            return;
        }

        if ($state === 'service_rename' && $svcId > 0) {
            $name = mb_substr($trimmed, 0, 64);
            $upd = ['display_label' => $name];
            if (Schema::hasColumn('svp_services', 'remark')) {
                $upd['remark'] = $name;
            }
            DB::table('svp_services')->where('id', $svcId)->update($upd);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.display_name_updated', $user, 'Name updated'));

            return;
        }

        // WP svc_al_pct / svc_al_exp / svc_al_ip threshold text states
        if ($state === 'service_alert_vol_pct' && is_numeric($trimmed) && $svcId > 0) {
            $pct = min(99, max(1, (int) $trimmed));
            $this->patchAlertField($user, $svcId, ['alert_low_pct' => $pct, 'alerts_volume' => true]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.alerts.threshold_saved', $user, 'Saved'));

            return;
        }
        if ($state === 'service_alert_exp_days' && $svcId > 0) {
            $days = preg_replace('/[^\d,\-]/', '', $trimmed) ?? '';
            $this->patchAlertField($user, $svcId, ['alert_expiry_days' => $days, 'alerts_expiry' => true]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.alerts.threshold_saved', $user, 'Saved'));

            return;
        }
        if ($state === 'service_alert_ip_pct' && is_numeric($trimmed) && $svcId > 0) {
            $pct = min(100, max(50, (int) $trimmed));
            $this->patchAlertField($user, $svcId, ['alert_ip_fill_pct' => $pct, 'alerts_users' => true]);
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.alerts.threshold_saved', $user, 'Saved'));
        }
    }

    /** Public entry for deferred live panel refresh (WP svc_panel_full_delivery). */
    public function deliverServiceCard(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        $this->sendServiceCard($ctx, $user, $chatId, $svc, false);
    }

    protected function sendServiceCard(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, bool $scheduleLive = true, int $msgId = 0): void
    {
        $label = ServiceNaming::formatServiceDisplayLabel($svc);
        $usedGb = round((int) ($svc->used_traffic ?? 0) / 1073741824, 1);
        $totalGb = round((int) ($svc->total_traffic ?? 0) / 1073741824, 1);
        $quotaHidden = \App\Support\PlanQuotaDisplay::isHiddenForService($svc);
        $totalDisplay = $quotaHidden
            ? \App\Support\PlanQuotaDisplay::unlimitedLabel()
            : $totalGb;
        $planName = $svc->plan_id ? (string) (SvpPlan::query()->find((int) $svc->plan_id)?->name ?? '') : '—';
        $note = trim((string) ($svc->service_note ?? ''));
        $notePreview = $note !== '' ? mb_substr($note, 0, 40) : '—';
        $status = (string) ($svc->status ?? 'active');
        $defaultDetail = $quotaHidden
            ? "Service #{id}\nLabel: {label}\nStatus: {status}\nEmail: {email}\nExpiry: {expiry}\nTraffic: {used} GB / {total}\nSlots: {slots}\nPlan: {plan}\nNote: {note}"
            : "Service #{id}\nLabel: {label}\nStatus: {status}\nEmail: {email}\nExpiry: {expiry}\nTraffic: {used}/{total} GB\nSlots: {slots}\nPlan: {plan}\nNote: {note}";
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.service.detail', $user, $defaultDetail),
            [
                'id' => $svc->id,
                'label' => $label,
                'status' => $status,
                'email' => (string) ($svc->email ?? ''),
                'expiry' => (string) ($svc->expires_at ?? '—'),
                'used' => $usedGb,
                'total' => $totalDisplay,
                'slots' => (int) ($svc->client_slots ?? 1),
                'plan' => $planName,
                'note' => $notePreview,
            ]
        );
        if ($quotaHidden) {
            $msg = preg_replace(
                '/Traffic:\s*[^\n]*/ui',
                'Traffic: '.$usedGb.' GB / '.\App\Support\PlanQuotaDisplay::unlimitedLabel(),
                $msg
            ) ?? $msg;
        }

        $isL2tp = (string) ($svc->service_type ?? '') === 'l2tp';
        $showAdmin = (bool) $user->admin_mode
            && $this->managed->isManagingOtherUser($user, (int) $svc->user_id);
        $fromId = (int) ($ctx->platform === 'bale' ? ($user->bale_user_id ?? 0) : ($user->tg_user_id ?? 0));
        $showPanelToggle = $showAdmin
            && $fromId > 0
            && $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)
            && ! $isL2tp;

        $markup = $this->uiLayout->buildInlineServiceMenu(
            (int) $svc->id,
            (string) $ctx->platform,
            $user,
            $isL2tp,
            $showAdmin,
            $showPanelToggle,
        );
        $extra = ['reply_markup' => $markup];

        if ($msgId > 0) {
            $edit = $this->runtime->editMessageText($ctx, [
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'text' => $msg,
                'reply_markup' => $markup,
            ]);
            if (! is_array($edit) || empty($edit['ok'])) {
                $this->runtime->sendMessage($ctx, $chatId, $msg, $extra);
            }
        } else {
            $this->runtime->sendMessage($ctx, $chatId, $msg, $extra);
        }
    }

    /** @param  array<string, mixed>  $result */
    protected function handleCheckoutResult(BotContext $ctx, SvpUser $user, int $chatId, array $result, int $svcId, string $title): void
    {
        if (! empty($result['free'])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.op_ok', $user, 'Done'));

            return;
        }
        if (! empty($result['ok']) && ! empty($result['transaction_id'])) {
            $txId = (int) $result['transaction_id'];
            $amount = (float) ($result['amount'] ?? 0);
            $this->state->set($user, 'service_checkout', [
                'transaction_id' => $txId,
                'service_id' => $svcId,
            ]);
            $this->checkoutUi->sendCheckoutSummary($ctx, $user, $chatId, $title.' #'.$svcId, $amount, $txId, 'svc');

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, '⛔ '.(string) ($result['message'] ?? $this->texts->getForUser('msg.common.failed', $user, 'Failed')));
    }

    protected function refreshServiceCheckout(BotContext $ctx, SvpUser $user, int $chatId, int $txId): void
    {
        $amount = $this->commerce->transactionAmount($txId);
        $meta = $this->commerce->transactionMeta($txId);
        $svcId = (int) ($meta['service_id'] ?? 0);
        $this->state->set($user, 'service_checkout', ['transaction_id' => $txId, 'service_id' => $svcId]);
        $this->checkoutUi->sendCheckoutSummary($ctx, $user, $chatId, 'Service #'.$svcId, $amount, $txId, 'svc');
    }

    protected function doRenew(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $result = $this->serviceCheckout->beginRenew($user, $ctx, $svcId);
        $this->handleCheckoutResult($ctx, $user, $chatId, $result, $svcId, 'Renew');
    }

    protected function confirmDelete(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.confirm_delete', $user, 'Delete service?'), [
            'reply_markup' => $this->keyboards->inline([
                [
                    ['text' => $this->texts->getForUser('btn.yes', $user, 'Yes'), 'callback_data' => 'svc:dely:'.$svcId],
                    ['text' => $this->texts->getForUser('btn.no', $user, 'No'), 'callback_data' => 'svc:m:'.$svcId],
                ],
            ]),
        ]);
    }

    protected function softDeleteService(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        if ($user->admin_mode && $this->managed->isManagingOtherUser($user, (int) (SvpService::query()->find($svcId)?->user_id ?? 0))) {
            $result = $this->mutate->applyForUser($user, 'service_delete', ['service_id' => $svcId]);
            $this->runtime->sendMessage($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        DB::table('svp_services')->where('id', $svcId)->update(['deleted_at' => now()]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.deleted', $user, 'Service removed'));
    }

    protected function handleRegenKey(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $fromId): void
    {
        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            $this->handleL2tpRotatePassword($ctx, $user, $chatId, $svc);

            return;
        }
        try {
            $panelId = max(1, (int) ($svc->panel_id ?? 1));
            $client = app(PanelClientFactory::class)->forPanelId($panelId);
            if (! method_exists($client, 'regenerateKey')) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));

                return;
            }
            $result = $client->regenerateKey((int) $svc->id);
            $this->runtime->sendMessage($ctx, $chatId, ! empty($result['ok'])
                ? $this->texts->getForUser('msg.svc.uuid_regenerated', $user, 'Key regenerated')
                : $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));
        } catch (\Throwable) {
            // TODO: WP xray_regenerate_key needs live panel session — surface failure when XUI unavailable.
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));
        }
    }

    protected function handleRegenSub(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $fromId): void
    {
        try {
            $panelId = max(1, (int) ($svc->panel_id ?? 1));
            $client = app(PanelClientFactory::class)->forPanelId($panelId);
            if (! method_exists($client, 'regenerateSubId')) {
                $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));

                return;
            }
            $result = $client->regenerateSubId((int) $svc->id);
            $this->runtime->sendMessage($ctx, $chatId, ! empty($result['ok'])
                ? $this->texts->getForUser('msg.svc.sub_id_regenerated', $user, 'Subscription id regenerated')
                : $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));
        } catch (\Throwable) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));
        }
    }

    /** @param  array<int, string>  $parts */
    protected function toggleAlert(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, array $parts): void
    {
        $kind = (string) ($parts[3] ?? '');
        $field = match ($kind) {
            'vol' => 'alerts_volume',
            'exp' => 'alerts_expiry',
            'users' => 'alerts_users',
            default => '',
        };
        if ($field === '') {
            return;
        }
        $current = (bool) ($svc->{$field} ?? false);
        $this->patchAlertField($user, (int) $svc->id, [$field => ! $current]);
        $fresh = SvpService::query()->find((int) $svc->id) ?? $svc;
        $this->alertsRenderMainPanel($ctx, $user, $chatId, 0, $fresh);
    }

    protected function mayAdminServiceOp(BotContext $ctx, SvpUser $user, SvpService $svc, int $fromId): bool
    {
        if (! (int) $user->admin_mode) {
            return false;
        }
        if ($fromId < 1 || ! $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            return false;
        }

        return $this->managed->isManagingOtherUser($user, (int) $svc->user_id);
    }

    protected function syncService(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->mutateService($ctx, $user, $chatId, 'service_panel_sync', $svcId);
    }

    protected function refreshServers(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        // WP svc:u — inbound_get refresh; prefer panel sync when admin mutate available, else ack.
        if ((bool) $user->admin_mode) {
            $this->mutateService($ctx, $user, $chatId, 'service_panel_sync', (int) $svc->id);

            return;
        }
        try {
            $panelId = max(1, (int) ($svc->panel_id ?? 1));
            $client = app(PanelClientFactory::class)->forPanelId($panelId);
            if (method_exists($client, 'refreshInbound')) {
                $client->refreshInbound([], (int) $svc->id);
            }
        } catch (\Throwable) {
            // Best-effort; still confirm to user like WP.
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.servers_refreshed', $user, 'Servers refreshed'));
    }

    protected function mutateService(BotContext $ctx, SvpUser $user, int $chatId, string $op, int $svcId): void
    {
        if (! $user->admin_mode && ! $this->managed->isManagingOtherUser($user, (int) (SvpService::query()->find($svcId)?->user_id ?? 0))) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.forbidden', $user, 'Access denied'));

            return;
        }
        $result = $this->mutate->applyForUser($user, $op, ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
    }

    protected function startAddVol(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_addvol', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_addvol', $user, 'Send extra GB'));
    }

    protected function dispatchAddVolume(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $svcId): void
    {
        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.volume_xray_only', $user, 'Volume add is for Xray only'));

            return;
        }
        $this->startAddVol($ctx, $user, $chatId, $svcId);
    }

    protected function dispatchAddSlots(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $svcId): void
    {
        if ((string) ($svc->service_type ?? '') === 'l2tp') {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.option_wrong_type', $user, 'Not available for this service type'));

            return;
        }
        $this->startAddUsers($ctx, $user, $chatId, $svcId);
    }

    protected function startAddUsers(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $unit = (float) app(\App\Services\SettingsStore::class)->get('price_per_extra_user', 0);
        if ($unit <= 0) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.extra_user_price_unset', $user, 'Extra user price is not set'));

            return;
        }
        $this->state->set($user, 'service_addusers', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.alerts.add_users_prompt', $user, 'Send extra slots'));
    }

    protected function startTransfer(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_transfer', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_transfer', $user, 'Send target user id'));
    }

    protected function createTransferCode(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        $sid = (int) $svc->id;
        $oid = (int) $svc->user_id;
        $code = null;
        if (Schema::hasTable('svp_service_transfer_codes')) {
            for ($i = 0; $i < 10; $i++) {
                $try = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $busy = (int) DB::table('svp_service_transfer_codes')
                    ->where('code', $try)
                    ->where('consumed', 0)
                    ->where('expires_at', '>', now())
                    ->count();
                if ($busy === 0) {
                    $code = $try;
                    DB::table('svp_service_transfer_codes')->insert([
                        'service_id' => $sid,
                        'owner_id' => $oid,
                        'code' => $code,
                        'expires_at' => now()->addMinutes(10),
                        'consumed' => 0,
                    ]);
                    break;
                }
            }
        } else {
            // TODO: persist to svp_service_transfer_codes when table is migrated; Cache bridge for now.
            $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put('svc_xfer_code:'.$code, ['service_id' => $sid, 'owner_id' => $oid], 600);
        }
        if ($code === null) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.transfer_code_fail', $user, 'Could not create transfer code'));

            return;
        }
        $txt = $this->texts->format(
            $this->texts->getForUser(
                'msg.svc.transfer_code',
                $user,
                "🎁 Service transfer\n🔑 Code: `{code}`\n⏳ Valid 10 minutes"
            ),
            ['code' => $code]
        );
        $this->runtime->sendMessage($ctx, $chatId, $txt);
    }

    protected function startLimitIp(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_limit_ip', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_limit_ip', $user, 'Send IP limit'));
    }

    protected function startRename(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_rename', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.prompt_display_name', $user, 'Send new display name'));
    }

    protected function startAlertWizard(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_alerts_pct', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_alerts', $user, 'Send traffic alert %'));
    }

    protected function startNote(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_note', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_note', $user, 'Send note text'));
    }

    protected function showUsagePanel(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $msgId = 0): void
    {
        $usedGb = round((int) ($svc->used_traffic ?? 0) / 1073741824, 2);
        $totalGb = round((int) ($svc->total_traffic ?? 0) / 1073741824, 2);
        $quotaHidden = \App\Support\PlanQuotaDisplay::isHiddenForService($svc);
        $text = $this->texts->format(
            $this->texts->getForUser(
                'msg.subscription_panel',
                $user,
                "📊 Usage\nTraffic: {used}/{total} GB\nExpiry: {expiry}\nSlots: {slots}"
            ),
            [
                'used' => $usedGb,
                'total' => $quotaHidden ? \App\Support\PlanQuotaDisplay::unlimitedLabel() : $totalGb,
                'expiry' => (string) ($svc->expires_at ?? '—'),
                'slots' => (int) ($svc->client_slots ?? 1),
                'email' => (string) ($svc->email ?? ''),
                'remark' => ServiceNaming::formatServiceDisplayLabel($svc),
                'sub_id' => (string) ($svc->sub_id ?? ''),
            ]
        );
        $markup = $this->keyboards->inline([
            [['text' => $this->texts->getForUser('btn.common.back', $user, 'Back'), 'callback_data' => 'svc:m:'.$svc->id]],
        ]);
        $extra = ['reply_markup' => $markup];
        if ($msgId > 0) {
            $edit = $this->runtime->editMessageText($ctx, array_merge([
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'text' => $text,
            ], $extra));
            if (is_array($edit) && ! empty($edit['ok'])) {
                return;
            }
        }
        $this->runtime->sendMessage($ctx, $chatId, $text, $extra);
    }

    protected function showActiveConnections(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        $ips = [];
        $email = trim((string) ($svc->email ?? ''));
        $panelId = max(1, (int) ($svc->panel_id ?? 1));
        try {
            if ($email !== '' && (svp_modules()->isEnabled('xui_panel') || svp_modules()->isEnabled('pasarguard'))) {
                $client = app(PanelClientFactory::class)->forPanelId($panelId);
                if (method_exists($client, 'runWithPanel')) {
                    $ips = $client->runWithPanel($panelId, function ($c) use ($email) {
                        if (method_exists($c, 'loginWithRetries') && ! $c->loginWithRetries(3, 200000)) {
                            return [];
                        }
                        if (! method_exists($c, 'clientIps')) {
                            return [];
                        }
                        $json = $c->clientIps($email);
                        if (method_exists($c, 'parseClientIpsResponse')) {
                            return $c->parseClientIpsResponse($json, 20);
                        }

                        return is_array($json) ? $json : [];
                    }) ?? [];
                } elseif (method_exists($client, 'clientIps')) {
                    $json = $client->clientIps($email);
                    $ips = method_exists($client, 'parseClientIpsResponse')
                        ? $client->parseClientIpsResponse($json, 20)
                        : (is_array($json) ? $json : []);
                }
            }
        } catch (\Throwable) {
            // TODO: live clientIps requires panel API — fall through to cached count message.
        }
        if (! is_array($ips)) {
            $ips = [];
        }
        $sep = "\n➖➖➖➖➖➖➖➖\n";
        $txt = '🌐 '.$this->texts->getForUser('btn.svc.active_connections', $user, 'Active connections').$sep;
        $txt .= $ips === []
            ? $this->texts->getForUser('msg.svc.no_active_ips', $user, '📭 No connections yet')
            : '• '.implode("\n• ", $ips);
        $this->runtime->sendMessage($ctx, $chatId, $txt);
    }

    protected function toggleAutorenew(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        $on = ! (int) ($svc->autorenew ?? 0);
        DB::table('svp_services')->where('id', (int) $svc->id)->update(['autorenew' => $on ? 1 : 0]);
        $this->runtime->sendMessage($ctx, $chatId, $on
            ? $this->texts->getForUser('msg.svc.auto_renew_on', $user, 'Auto-renew enabled')
            : $this->texts->getForUser('msg.svc.auto_renew_off', $user, 'Auto-renew disabled'));
    }

    protected function togglePanelEnable(BotContext $ctx, SvpUser $user, int $chatId, int $msgId, SvpService $svc, int $fromId): void
    {
        if (! $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            return;
        }
        $cur = (int) ($svc->panel_client_enabled ?? 1);
        $want = $cur === 1 ? 0 : 1;
        $result = $this->mutate->applyForUser($user, 'user_service_toggle_enable', [
            'service_id' => (int) $svc->id,
            'enabled' => $want === 1,
        ]);
        if (empty($result['ok'])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.panel_update_fail', $user, 'Panel update failed'));

            return;
        }
        DB::table('svp_services')->where('id', (int) $svc->id)->update(['panel_client_enabled' => $want]);
        $fresh = SvpService::query()->find((int) $svc->id) ?? $svc;
        $this->sendServiceCard($ctx, $user, $chatId, $fresh, false, $msgId);
    }

    protected function handleL2tpRotatePassword(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        if (! svp_modules()->isEnabled('l2tp') || ! method_exists($this->l2tpProvisioner, 'rotatePassword')) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser(
                'msg.svc.l2tp_password_ssh_required',
                $user,
                'L2TP password rotation requires SSH provisioning on the server. Contact support.'
            ));

            return;
        }

        $result = $this->l2tpProvisioner->rotatePassword($svc);
        if (empty($result['ok'])) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser(
                'msg.svc.l2tp_password_ssh_required',
                $user,
                'L2TP password rotation requires SSH provisioning on the server. Contact support.'
            ));

            return;
        }

        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.l2tp_password_ok', $user, 'Password updated'));
        $this->delivery->enqueue($ctx, $user, $chatId, (int) $svc->id, 'config');
    }

    protected function alertsRenderMainPanel(BotContext $ctx, SvpUser $user, int $chatId, int $msgId, SvpService $svc): void
    {
        $isL2 = (string) ($svc->service_type ?? '') === 'l2tp';
        $sid = (int) $svc->id;
        $vol = $this->alertsHelper->volumeEnabled($svc);
        $exp = $this->alertsHelper->expiryAlertEnabled($svc);
        $usr = $this->alertsHelper->usersAlertEnabled($svc);
        $text = $this->texts->getForUser('msg.alerts.panel_intro', $user, 'Service alerts');
        $rows = [
            [
                ['text' => '📊 '.($vol ? 'Vol off' : 'Vol on'), 'callback_data' => 'svc:a1:'.$sid],
                ['text' => '⏰ '.($exp ? 'Exp off' : 'Exp on'), 'callback_data' => 'svc:a2:'.$sid],
            ],
        ];
        if (! $isL2) {
            $rows[] = [
                ['text' => '👥 '.($usr ? 'Users off' : 'Users on'), 'callback_data' => 'svc:a3:'.$sid],
                ['text' => '⚙️ Thresholds', 'callback_data' => 'svc:a0:'.$sid],
            ];
        } else {
            $rows[] = [['text' => '⚙️ Thresholds', 'callback_data' => 'svc:a0:'.$sid]];
        }
        $rows[] = [['text' => $this->texts->getForUser('btn.common.back', $user, 'Back'), 'callback_data' => 'svc:m:'.$sid]];
        $extra = ['reply_markup' => $this->keyboards->inline($rows)];
        if ($msgId > 0) {
            $edit = $this->runtime->editMessageText($ctx, array_merge([
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'text' => $text,
            ], $extra));
            if (is_array($edit) && ! empty($edit['ok'])) {
                return;
            }
        }
        $this->runtime->sendMessage($ctx, $chatId, $text, $extra);
    }

    protected function alertsRenderThresholdsPanel(BotContext $ctx, SvpUser $user, int $chatId, int $msgId, SvpService $svc): void
    {
        $isL2 = (string) ($svc->service_type ?? '') === 'l2tp';
        $sid = (int) $svc->id;
        $text = $this->texts->getForUser('msg.alerts.thresholds_intro', $user, 'Alert thresholds');
        $rows = [
            [
                ['text' => '📉 Volume %', 'callback_data' => 'svc:a5:'.$sid],
                ['text' => '📅 Expiry days', 'callback_data' => 'svc:a6:'.$sid],
            ],
        ];
        if (! $isL2) {
            $rows[] = [['text' => '👥 User limit %', 'callback_data' => 'svc:a7:'.$sid]];
        }
        $rows[] = [['text' => '↩️ Back', 'callback_data' => 'svc:a8:'.$sid]];
        $extra = ['reply_markup' => $this->keyboards->inline($rows)];
        if ($msgId > 0) {
            $this->runtime->editMessageText($ctx, array_merge([
                'chat_id' => $chatId,
                'message_id' => $msgId,
                'text' => $text,
            ], $extra));

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, $text, $extra);
    }

    protected function handleAlertSubCallback(BotContext $ctx, SvpUser $user, int $chatId, int $msgId, SvpService $svc, string $action): void
    {
        $sid = (int) $svc->id;
        $isL2 = (string) ($svc->service_type ?? '') === 'l2tp';

        if ($action === 'a1') {
            $this->patchAlertField($user, $sid, ['alerts_volume' => ! $this->alertsHelper->volumeEnabled($svc)]);
            $fresh = SvpService::query()->find($sid) ?? $svc;
            $this->alertsRenderMainPanel($ctx, $user, $chatId, $msgId, $fresh);

            return;
        }
        if ($action === 'a2') {
            $this->patchAlertField($user, $sid, ['alerts_expiry' => ! $this->alertsHelper->expiryAlertEnabled($svc)]);
            $fresh = SvpService::query()->find($sid) ?? $svc;
            $this->alertsRenderMainPanel($ctx, $user, $chatId, $msgId, $fresh);

            return;
        }
        if ($action === 'a3') {
            if ($isL2) {
                return;
            }
            $this->patchAlertField($user, $sid, ['alerts_users' => ! $this->alertsHelper->usersAlertEnabled($svc)]);
            $fresh = SvpService::query()->find($sid) ?? $svc;
            $this->alertsRenderMainPanel($ctx, $user, $chatId, $msgId, $fresh);

            return;
        }
        if ($action === 'a0') {
            $this->alertsRenderThresholdsPanel($ctx, $user, $chatId, $msgId, $svc);

            return;
        }
        if ($action === 'a8') {
            $this->alertsRenderMainPanel($ctx, $user, $chatId, $msgId, $svc);

            return;
        }
        if ($action === 'a5') {
            $this->state->set($user, 'service_alert_vol_pct', ['service_id' => $sid]);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
                $this->texts->getForUser('msg.alerts.threshold_volume_prompt', $user, 'Send volume alert % (now {pct})'),
                ['pct' => (string) $this->alertsHelper->effectiveLowTrafficPct($svc)]
            ));

            return;
        }
        if ($action === 'a6') {
            $this->state->set($user, 'service_alert_exp_days', ['service_id' => $sid]);
            $days = implode(',', $this->alertsHelper->effectiveExpiryDays($svc));
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
                $this->texts->getForUser('msg.alerts.threshold_expiry_prompt', $user, 'Send expiry alert days (now {days})'),
                ['days' => $days]
            ));

            return;
        }
        if ($action === 'a7') {
            if ($isL2) {
                return;
            }
            $this->state->set($user, 'service_alert_ip_pct', ['service_id' => $sid]);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
                $this->texts->getForUser('msg.alerts.threshold_ip_prompt', $user, 'Send user-limit alert % (now {pct})'),
                ['pct' => (string) $this->alertsHelper->effectiveIpFillPct($svc)]
            ));
        }
    }

    /** @param  array<string, mixed>  $fields */
    protected function patchAlertField(SvpUser $user, int $svcId, array $fields): void
    {
        $row = DB::table('svp_services')->where('id', $svcId)->first();
        $existing = [];
        if ($row && ! empty($row->alerts_json)) {
            $dec = json_decode((string) $row->alerts_json, true);
            $existing = is_array($dec) ? $dec : [];
        }
        $merged = array_merge($existing, $fields);
        $any = ! empty($merged['alerts_volume']) || ! empty($merged['alerts_expiry']) || ! empty($merged['alerts_users']);
        $merged['alerts_enabled'] = $any;
        $upd = ['alerts_json' => json_encode($merged)];
        foreach (['alerts_volume', 'alerts_expiry', 'alerts_users', 'alerts_enabled', 'alert_low_pct', 'alert_expiry_days', 'alert_ip_fill_pct'] as $col) {
            if (array_key_exists($col, $fields) && Schema::hasColumn('svp_services', $col)) {
                $upd[$col] = $fields[$col];
            }
        }
        if (Schema::hasColumn('svp_services', 'alerts_enabled')) {
            $upd['alerts_enabled'] = $any ? 1 : 0;
        }
        DB::table('svp_services')->where('id', $svcId)->update($upd);
    }

    protected function handleConfigCopyAll(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, string $cbId): void
    {
        if ($cbId !== '') {
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => $this->texts->getForUser('msg.service.processing', $user, '⏳'),
            ]);
        }
        $owner = SvpUser::query()->find((int) $svc->user_id) ?? $user;
        $data = $this->uriCollector->collect($owner, (int) $svc->id);
        $uris = is_array($data['uris'] ?? null) ? $data['uris'] : [];
        $lines = [];
        foreach ($uris as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $lines[] = $u;
            }
        }
        if ($lines === []) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.config_unavailable', $user, 'Config unavailable'));

            return;
        }
        $plain = implode("\n", $lines);
        if (strlen($plain) <= 4096) {
            $this->runtime->sendMessage($ctx, $chatId, '<pre>'.e($plain).'</pre>');

            return;
        }
        // Long payloads: send truncated notice + first chunk (document API not wired on BotRuntime).
        $this->runtime->sendMessage($ctx, $chatId, mb_substr($plain, 0, 3500)."\n…");
    }

    protected function handleConfigWire(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $idx, string $cbId): void
    {
        if ($cbId !== '') {
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => $this->texts->getForUser('msg.service.processing', $user, '⏳'),
            ]);
        }
        $owner = SvpUser::query()->find((int) $svc->user_id) ?? $user;
        $data = $this->uriCollector->collect($owner, (int) $svc->id);
        $uris = is_array($data['uris'] ?? null) ? array_values($data['uris']) : [];
        $uri = isset($uris[$idx]) ? trim((string) $uris[$idx]) : '';
        if ($uri === '') {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.svc.config_unavailable', $user, 'Config unavailable'));

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, '<pre>'.e($uri).'</pre>');
    }

    protected function promptWalletPartial(BotContext $ctx, SvpUser $user, int $chatId, int $txId): void
    {
        $tx = DB::table('svp_transactions')->where('id', $txId)->first();
        $amount = (float) ($tx->amount ?? 0);
        $balance = (float) $user->balance;
        if ($balance >= $amount) {
            $result = $this->commerce->payWithWallet($user, $txId, false);
            $this->handleWalletResult($ctx, $user, $chatId, $result);

            return;
        }
        if ($balance <= 0) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.insufficient_balance', $user, 'Insufficient balance'));

            return;
        }
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->format(
            $this->texts->getForUser('msg.buy.wallet_partial_confirm', $user, 'Use {balance} from wallet?'),
            ['balance' => number_format($balance), 'remaining' => number_format(max(0, $amount - $balance))]
        ), [
            'reply_markup' => $this->keyboards->inline([
                [
                    ['text' => $this->texts->getForUser('btn.yes', $user, 'Yes'), 'callback_data' => 'svc:swy:'.$txId],
                    ['text' => $this->texts->getForUser('btn.no', $user, 'No'), 'callback_data' => 'svc:swn:'.$txId],
                ],
            ]),
        ]);
    }

    /** @param  array<string, mixed>  $result */
    protected function handleWalletResult(BotContext $ctx, SvpUser $user, int $chatId, array $result): void
    {
        $this->state->clear($user);
        $this->runtime->sendMessage($ctx, $chatId, ! empty($result['ok'])
            ? $this->texts->getForUser('msg.buy.wallet_paid', $user, 'Paid from wallet.')
            : $this->texts->getForUser('msg.buy.insufficient_balance', $user, 'Insufficient balance'));
    }

    protected function resolveService(BotContext $ctx, SvpUser $user, int $svcId, int $fromId = 0): ?SvpService
    {
        if ($svcId < 1) {
            return null;
        }
        $svc = SvpService::query()->find($svcId);
        if (! $svc) {
            return null;
        }
        if ((int) $svc->user_id === (int) $user->id) {
            return $svc;
        }
        if ($fromId > 0 && $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)
            && $this->managed->isManagingOtherUser($user, (int) $svc->user_id)) {
            return $svc;
        }

        return null;
    }
}
