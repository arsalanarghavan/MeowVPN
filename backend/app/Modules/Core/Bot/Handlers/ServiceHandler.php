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
use App\Modules\Core\Bot\Services\KeyboardBuilder;
use App\Modules\Core\Bot\Services\TextService;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\DB;

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

        match ($action) {
            'lp' => $this->listServices($ctx, $user, $chatId, $svcId),
            'm', 'v' => $this->sendServiceCard($ctx, $user, $chatId, $svc),
            'p' => $this->delivery->enqueue($ctx, $user, $chatId, (int) $svc->id, 'config', (string) ($payload['cb_id'] ?? '')),
            'l' => $this->delivery->enqueue($ctx, $user, $chatId, (int) $svc->id, strlen((string) ($svc->sub_url ?? '')) > 4000 ? 'subscription' : 'link', (string) ($payload['cb_id'] ?? '')),
            'q', 'cfg' => $this->delivery->enqueue($ctx, $user, $chatId, (int) $svc->id, 'config'),
            'r' => $this->doRenew($ctx, $user, $chatId, $svcId),
            'av' => $this->startAddVol($ctx, $user, $chatId, $svcId),
            'us' => $this->startAddUsers($ctx, $user, $chatId, $svcId),
            'al' => $this->startAlertWizard($ctx, $user, $chatId, $svcId),
            'nt', 'n' => $this->startNote($ctx, $user, $chatId, $svcId),
            'del' => $this->confirmDelete($ctx, $user, $chatId, $svcId),
            'dely' => $this->softDeleteService($ctx, $user, $chatId, $svcId),
            'xf' => $this->startTransfer($ctx, $user, $chatId, $svcId),
            'li' => $this->startLimitIp($ctx, $user, $chatId, $svcId),
            'sync' => $this->syncService($ctx, $user, $chatId, $svcId),
            'rk' => $this->handleRegenKey($ctx, $user, $chatId, $svc, $fromId),
            'rs' => $this->handleRegenSub($ctx, $user, $chatId, $svc, $fromId),
            'alt' => $this->toggleAlert($ctx, $user, $chatId, $svc, (array) ($payload['parts'] ?? [])),
            'sub' => $this->delivery->enqueue($ctx, $user, $chatId, (int) $svc->id, 'subscription', (string) ($payload['cb_id'] ?? '')),
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
        }
    }

    protected function sendServiceCard(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc): void
    {
        $label = ServiceNaming::formatServiceDisplayLabel($svc);
        $usedGb = round((int) ($svc->used_traffic ?? 0) / 1073741824, 1);
        $totalGb = round((int) ($svc->total_traffic ?? 0) / 1073741824, 1);
        $planName = $svc->plan_id ? (string) (SvpPlan::query()->find((int) $svc->plan_id)?->name ?? '') : '—';
        $note = trim((string) ($svc->service_note ?? ''));
        $notePreview = $note !== '' ? mb_substr($note, 0, 40) : '—';
        $status = (string) ($svc->status ?? 'active');
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.service.detail', $user, "Service #{id}\nLabel: {label}\nStatus: {status}\nEmail: {email}\nExpiry: {expiry}\nTraffic: {used}/{total} GB\nSlots: {slots}\nPlan: {plan}\nNote: {note}"),
            [
                'id' => $svc->id,
                'label' => $label,
                'status' => $status,
                'email' => (string) ($svc->email ?? ''),
                'expiry' => (string) ($svc->expires_at ?? '—'),
                'used' => $usedGb,
                'total' => $totalGb,
                'slots' => (int) ($svc->client_slots ?? 1),
                'plan' => $planName,
                'note' => $notePreview,
            ]
        );
        $sid = (int) $svc->id;
        $rows = [
                [
                    ['text' => $this->texts->getForUser('btn.service.config', $user, 'Config'), 'callback_data' => 'svc:p:'.$sid],
                    ['text' => $this->texts->getForUser('btn.service.link', $user, 'Link'), 'callback_data' => 'svc:l:'.$sid],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.service.renew', $user, 'Renew'), 'callback_data' => 'svc:r:'.$sid],
                    ['text' => $this->texts->getForUser('btn.service.addvol', $user, '+GB'), 'callback_data' => 'svc:av:'.$sid],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.service.addusers', $user, '+Users'), 'callback_data' => 'svc:us:'.$sid],
                    ['text' => $this->texts->getForUser('btn.service.alerts', $user, 'Alerts'), 'callback_data' => 'svc:al:'.$sid],
                ],
                [
                    ['text' => '🔔 Vol', 'callback_data' => 'svc:alt:'.$sid.':vol'],
                    ['text' => '🔔 Exp', 'callback_data' => 'svc:alt:'.$sid.':exp'],
                ],
                [['text' => '🔔 Users', 'callback_data' => 'svc:alt:'.$sid.':users']],
                [
                    ['text' => $this->texts->getForUser('btn.service.transfer', $user, 'Transfer'), 'callback_data' => 'svc:xf:'.$sid],
                    ['text' => $this->texts->getForUser('btn.service.limit_ip', $user, 'Limit IP'), 'callback_data' => 'svc:li:'.$sid],
                ],
                [['text' => $this->texts->getForUser('btn.service.note', $user, 'Note'), 'callback_data' => 'svc:nt:'.$sid]],
                [
                    ['text' => $this->texts->getForUser('btn.service.sync', $user, 'Sync'), 'callback_data' => 'svc:sync:'.$sid],
                    ['text' => $this->texts->getForUser('btn.service.delete', $user, 'Delete'), 'callback_data' => 'svc:del:'.$sid],
                ],
                [['text' => '◀', 'callback_data' => 'svc:lp:0']],
        ];
        $this->runtime->sendMessage($ctx, $chatId, $msg, [
            'reply_markup' => $this->keyboards->inline($rows),
        ]);
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
        if (! $this->mayAdminServiceOp($ctx, $user, $svc, $fromId)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.forbidden', $user, 'Access denied'));

            return;
        }
        $this->mutateService($ctx, $user, $chatId, 'service_regen_key', (int) $svc->id);
    }

    protected function handleRegenSub(BotContext $ctx, SvpUser $user, int $chatId, SvpService $svc, int $fromId): void
    {
        if (! $this->mayAdminServiceOp($ctx, $user, $svc, $fromId)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.forbidden', $user, 'Access denied'));

            return;
        }
        $this->mutateService($ctx, $user, $chatId, 'service_regen_sub_id', (int) $svc->id);
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
        $result = $this->mutate->applyForUser($user, 'service_alerts_patch', [
            'service_id' => (int) $svc->id,
            'alerts' => [$field => ! $current],
        ]);
        $this->runtime->sendMessage($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
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

    protected function startAddUsers(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_addusers', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_addusers', $user, 'Send extra slots'));
    }

    protected function startTransfer(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_transfer', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_transfer', $user, 'Send target user id'));
    }

    protected function startLimitIp(BotContext $ctx, SvpUser $user, int $chatId, int $svcId): void
    {
        $this->state->set($user, 'service_limit_ip', ['service_id' => $svcId]);
        $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.service.prompt_limit_ip', $user, 'Send IP limit'));
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
