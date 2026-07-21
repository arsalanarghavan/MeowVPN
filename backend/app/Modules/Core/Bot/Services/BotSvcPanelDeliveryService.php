<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpPlan;
use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Jobs\DeferredSvcPanelDeliveryJob;
use App\Support\PlanQuotaDisplay;
use App\Support\Xui\ServiceNaming;
use Illuminate\Support\Facades\Log;

/**
 * WP schedule_svc_panel_full_delivery / run_svc_panel_full_delivery.
 */
class BotSvcPanelDeliveryService
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected KeyboardBuilder $keyboards,
        protected BotConfigDeliveryService $configDelivery,
        protected BotManagedUserContext $managed,
        protected UiLayoutService $uiLayout,
        protected AdminGuard $adminGuard,
    ) {}

    public function schedule(
        BotContext $ctx,
        SvpUser $user,
        int $chatId,
        int $panelMsgId,
        SvpService $svc,
        string $action = 'p',
        ?string $cbId = null,
    ): void {
        if ($cbId) {
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => $this->texts->getForUser('msg.service.processing', $user, '⏳'),
            ]);
        }

        $ownerId = (int) $svc->user_id;
        if ($this->managed->isManagingOtherUser($user, $ownerId)) {
            $ownerId = (int) $user->id;
        }

        DeferredSvcPanelDeliveryJob::dispatch(
            (string) $ctx->platform,
            $chatId,
            max(0, $panelMsgId),
            (int) $svc->id,
            $ownerId,
            (int) $user->id,
            $action,
        );
    }

    public function run(
        string $platform,
        int $chatId,
        int $panelMsgId,
        int $serviceId,
        int $ownerUserId,
        int $actingUserId,
        string $action = 'p',
    ): void {
        $user = SvpUser::query()->find($actingUserId);
        if (! $user) {
            return;
        }
        $ctx = new BotContext($platform);

        try {
            $svc = SvpService::query()->where('id', $serviceId)->whereNull('deleted_at')->first();
            if (! $svc) {
                $this->notifyFailed($ctx, $chatId, $panelMsgId, $user, 'msg.service.not_found', 'Service not found');

                return;
            }

            if (in_array($action, ['l', 'q', 'cfg', 'sub'], true)) {
                $mode = $action === 'l'
                    ? (strlen((string) ($svc->sub_url ?? '')) > 4000 ? 'subscription' : 'link')
                    : ($action === 'sub' ? 'subscription' : 'config');
                $this->configDelivery->enqueue($ctx, $user, $chatId, $serviceId, $mode);

                return;
            }

            // action = p (and default): refresh/edit live service panel message.
            [$text, $markup] = $this->buildPanelMessage($ctx, $user, $svc);
            $extra = ['reply_markup' => $markup];

            if ($panelMsgId > 0) {
                $edit = $this->runtime->editMessageText($ctx, [
                    'chat_id' => $chatId,
                    'message_id' => $panelMsgId,
                    'text' => $text,
                    'reply_markup' => $extra['reply_markup'],
                ]);
                if (! is_array($edit) || empty($edit['ok'])) {
                    $this->runtime->sendMessage($ctx, $chatId, $text, $extra);
                }
            } else {
                $this->runtime->sendMessage($ctx, $chatId, $text, $extra);
            }

            if ($platform === 'telegram') {
                $this->configDelivery->enqueue($ctx, $user, $chatId, $serviceId, 'config');
            }
        } catch (\Throwable $e) {
            Log::error('svc_panel_full_delivery failed', [
                'service_id' => $serviceId,
                'action' => $action,
                'm' => $e->getMessage(),
            ]);
            $this->notifyFailed(
                $ctx,
                $chatId,
                $panelMsgId,
                $user,
                'msg.service.config_send_fail',
                'Service panel load failed. Please try again.'
            );
        }
    }

    /**
     * @return array{0:string,1:array{inline_keyboard: list<list<array<string, mixed>>>}}
     */
    protected function buildPanelMessage(BotContext $ctx, SvpUser $user, SvpService $svc): array
    {
        $label = ServiceNaming::formatServiceDisplayLabel($svc);
        $usedGb = round((int) ($svc->used_traffic ?? 0) / 1073741824, 1);
        $totalGb = round((int) ($svc->total_traffic ?? 0) / 1073741824, 1);
        $quotaHidden = PlanQuotaDisplay::isHiddenForService($svc);
        $totalDisplay = $quotaHidden ? PlanQuotaDisplay::unlimitedLabel() : $totalGb;
        $planName = $svc->plan_id ? (string) (SvpPlan::query()->find((int) $svc->plan_id)?->name ?? '') : '—';
        $note = trim((string) ($svc->service_note ?? ''));
        $notePreview = $note !== '' ? mb_substr($note, 0, 40) : '—';
        $defaultDetail = $quotaHidden
            ? "Service #{id}\nLabel: {label}\nStatus: {status}\nEmail: {email}\nExpiry: {expiry}\nTraffic: {used} GB / {total}\nSlots: {slots}\nPlan: {plan}\nNote: {note}"
            : "Service #{id}\nLabel: {label}\nStatus: {status}\nEmail: {email}\nExpiry: {expiry}\nTraffic: {used}/{total} GB\nSlots: {slots}\nPlan: {plan}\nNote: {note}";
        $msg = $this->texts->format(
            $this->texts->getForUser('msg.service.detail', $user, $defaultDetail),
            [
                'id' => $svc->id,
                'label' => $label,
                'status' => (string) ($svc->status ?? 'active'),
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
                'Traffic: '.$usedGb.' GB / '.PlanQuotaDisplay::unlimitedLabel(),
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

        return [$msg, $markup];
    }

    protected function notifyFailed(
        BotContext $ctx,
        int $chatId,
        int $panelMsgId,
        SvpUser $user,
        string $textKey,
        string $fallback,
    ): void {
        $text = $this->texts->getForUser($textKey, $user, $fallback);
        if ($panelMsgId > 0) {
            $edit = $this->runtime->editMessageText($ctx, [
                'chat_id' => $chatId,
                'message_id' => $panelMsgId,
                'text' => $text,
            ]);
            if (is_array($edit) && ! empty($edit['ok'])) {
                return;
            }
        }
        $this->runtime->sendMessage($ctx, $chatId, $text);
    }
}
