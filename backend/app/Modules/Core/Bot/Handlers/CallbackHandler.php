<?php

namespace App\Modules\Core\Bot\Handlers;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminHandlerRegistry;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\AdminGuard;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\AdminReceiptReviewService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;

class CallbackHandler
{
    public function __construct(
        protected BotRuntime $runtime,
        protected AdminGuard $adminGuard,
        protected BotStateService $state,
        protected BuyHandler $buy,
        protected ServiceHandler $service,
        protected WalletHandler $wallet,
        protected SupportHandler $support,
        protected SyncHandler $sync,
        protected AdminHandlerRegistry $adminRegistry,
        protected BotAdminMutateService $mutate,
        protected AdminKeyboardBuilder $keyboards,
        protected AdminPermissionService $permissions,
        protected AdminBotScopeService $scope,
        protected AdminReceiptReviewService $receiptReview,
        protected TextService $texts,
    ) {}

    /** @param  array<string, mixed>  $payload */
    public function handle(BotContext $ctx, array $payload): void
    {
        $cb = (array) ($payload['cb'] ?? []);
        $user = $payload['user'] ?? null;
        $from = (array) ($payload['from'] ?? ($cb['from'] ?? []));
        $fromId = (int) ($from['id'] ?? 0);
        $data = (string) ($cb['data'] ?? '');
        $cbId = (string) ($cb['id'] ?? '');
        $msg = (array) ($cb['message'] ?? []);
        $chatId = (int) ($msg['chat']['id'] ?? ($payload['chat_id'] ?? 0));
        $msgId = (int) ($msg['message_id'] ?? 0);

        if ($data === 'noop' || str_starts_with($data, 'alnoop:')) {
            $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId]);

            return;
        }

        $defer = str_starts_with($data, 'rc:')
            || str_starts_with($data, 'buy:cf:')
            || str_starts_with($data, 'buy:pm:')
            || str_starts_with($data, 'buy:sw')
            || str_starts_with($data, 'buy:bw:')
            || str_starts_with($data, 'svc:p:')
            || str_starts_with($data, 'svc:l:');
        if (! $defer) {
            $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId]);
        }

        if (str_starts_with($data, 'chjoin:')) {
            return;
        }

        $parts = explode(':', $data);
        $head = $parts[0] ?? '';

        if ($head === 'wal' && $user instanceof SvpUser) {
            if (($parts[1] ?? '') === 'h') {
                $off = isset($parts[2]) ? (int) $parts[2] : 0;
                $this->wallet->showHistory($ctx, $user, $chatId, $off);
            } elseif (($parts[1] ?? '') === 'tu') {
                $this->wallet->beginTopup($ctx, $user, $chatId);
            } elseif (($parts[1] ?? '') === 'cr') {
                $this->wallet->beginCryptoTopup($ctx, $user, $chatId);
            } elseif (($parts[1] ?? '') === 'f' && isset($parts[2])) {
                $filter = in_array($parts[2], ['purchase', 'topup', 'all'], true) ? $parts[2] : 'all';
                $off = isset($parts[3]) ? (int) $parts[3] : 0;
                $this->wallet->showHistoryFiltered($ctx, $user, $chatId, $filter, $off);
            }

            return;
        }

        if ($head === 'sup' && $user instanceof SvpUser) {
            if (($parts[1] ?? '') === 'c') {
                $this->support->showContact($ctx, $user, $chatId);
            } else {
                $this->support->showFaq($ctx, $user, $chatId);
            }

            return;
        }

        if ($head === 'sync' && $user instanceof SvpUser) {
            if (($parts[1] ?? '') === 'g') {
                $this->sync->generateCode($ctx, $user, $chatId);
            } elseif (($parts[1] ?? '') === 'i') {
                $this->sync->promptCode($ctx, $user, $chatId);
            }

            return;
        }

        if ($head === 'reg' && isset($parts[1], $parts[2])) {
            if (! $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
                return;
            }
            $this->adminRegistry->handleRegistration($ctx, $parts[1], (int) $parts[2], $from, $chatId, $cbId);

            return;
        }

        if ($head === 'rc' && isset($parts[1], $parts[2])) {
            if (! $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
                $this->runtime->answerCallbackQuery($ctx, [
                    'callback_query_id' => $cbId,
                    'text' => $this->texts->get('msg.admin.denied_permission', 'Forbidden'),
                    'show_alert' => true,
                ]);

                return;
            }
            $this->handleReceiptCallback($ctx, $parts, $from, $chatId, $msgId, $cbId);

            return;
        }

        if ($head === 'buy' && $user instanceof SvpUser) {
            $this->buy->handleCallback($ctx, $user, [
                'parts' => $parts,
                'chat_id' => $chatId,
                'msg_id' => $msgId,
                'cb_id' => $cbId,
                'callback_query_id' => $cbId,
            ]);

            return;
        }

        if ($head === 'svc' && isset($parts[1])) {
            if ($user instanceof SvpUser && $this->service->handlePaymentParts($ctx, $user, $parts, $chatId, $cbId)) {
                return;
            }
            if (($parts[1] ?? '') === 'lp' && isset($parts[2]) && $user instanceof SvpUser) {
                $this->service->listServices($ctx, $user, $chatId, (int) $parts[2]);

                return;
            }
            if (isset($parts[2]) && $user instanceof SvpUser) {
                $this->service->handleCallback($ctx, $user, [
                    'action' => (string) $parts[1],
                    'svc_id' => (int) $parts[2],
                    'parts' => $parts,
                    'chat_id' => $chatId,
                    'msg_id' => $msgId,
                    'from_id' => $fromId,
                    'cb_id' => $cbId,
                ]);
            }

            return;
        }

        if ($head === 'pnl' && $this->adminGuard->isPlatformAdmin($ctx->platform, $fromId)) {
            $this->adminRegistry->handlePnl($ctx, $parts, $user, $chatId, $msgId, $fromId);

            return;
        }
    }

    /** @param  array<int, string>  $parts */
    protected function handleReceiptCallback(BotContext $ctx, array $parts, array $from, int $chatId, int $msgId, string $cbId): void
    {
        $action = (string) ($parts[1] ?? '');
        $rid = (int) ($parts[2] ?? 0);
        $fromId = (int) ($from['id'] ?? 0);
        $label = (string) ($from['username'] ?? $from['first_name'] ?? 'admin');

        $admin = $this->adminGuard->resolveAdminByPlatformId($ctx->platform, $fromId);
        if (! $admin) {
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => $this->texts->get('msg.admin.denied_permission', 'Forbidden'),
                'show_alert' => true,
            ]);

            return;
        }
        $this->scope->setActingAdmin((int) $admin->id);

        if ($action === 'rb') {
            $rec = DB::table('svp_receipts')->where('id', $rid)->first();
            if ($rec && in_array((string) $rec->status, ['pending', 'processing'], true)) {
                $markup = $this->keyboards->inlineReceipt($rid);
                $this->receiptReview->editAdminMessages($ctx, $rec, $markup);
                if ($msgId > 0) {
                    $this->runtime->editMessageReplyMarkup($ctx, [
                        'chat_id' => $chatId,
                        'message_id' => $msgId,
                        'reply_markup' => $markup,
                    ]);
                }
            }
            $this->runtime->answerCallbackQuery($ctx, ['callback_query_id' => $cbId]);

            return;
        }

        if ($action === 'rr' && isset($parts[3])) {
            $reason = (string) $parts[2];
            $rid = (int) $parts[3];
            if (! $this->permissions->mayCallOp($admin, 'receipt_review')) {
                $this->runtime->answerCallbackQuery($ctx, [
                    'callback_query_id' => $cbId,
                    'text' => $this->texts->get('msg.admin.denied_permission', 'Forbidden'),
                    'show_alert' => true,
                ]);

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'receipt_action', [
                'receipt_id' => $rid,
                'action' => 'reject',
                'reject_reason' => $reason,
                'admin_label' => $label,
            ]);
            $rec = DB::table('svp_receipts')->where('id', $rid)->first();
            if ($rec) {
                $done = ['inline_keyboard' => [[['text' => '❌ '.$reason, 'callback_data' => 'noop']]]];
                $this->receiptReview->editAdminMessages($ctx, $rec, $done);
            }
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => ! empty($result['ok'])
                    ? $this->texts->get('msg.receipt.rejected', 'Rejected')
                    : $this->texts->get('msg.common.failed', 'Failed'),
            ]);

            return;
        }

        if ($action === 'a') {
            if (! $this->permissions->mayCallOp($admin, 'receipt_review')) {
                $this->runtime->answerCallbackQuery($ctx, [
                    'callback_query_id' => $cbId,
                    'text' => $this->texts->get('msg.admin.denied_permission', 'Forbidden'),
                    'show_alert' => true,
                ]);

                return;
            }
            $result = $this->mutate->applyForUser($admin, 'receipt_action', [
                'receipt_id' => $rid,
                'action' => 'approve',
                'admin_label' => $label,
            ]);
            $rec = DB::table('svp_receipts')->where('id', $rid)->first();
            if ($rec && ! empty($result['ok'])) {
                $done = ['inline_keyboard' => [[['text' => '✅', 'callback_data' => 'noop']]]];
                $this->receiptReview->editAdminMessages($ctx, $rec, $done);
            }
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => ! empty($result['ok'])
                    ? $this->texts->get('msg.receipt.approved', 'Approved')
                    : (string) ($result['message'] ?? $result['reason'] ?? $this->texts->get('msg.common.failed', 'Failed')),
            ]);

            return;
        }

        if ($action === 'r') {
            $rec = DB::table('svp_receipts')->where('id', $rid)->first();
            if (! $rec || ! in_array((string) $rec->status, ['pending', 'processing'], true)) {
                $this->runtime->answerCallbackQuery($ctx, [
                    'callback_query_id' => $cbId,
                    'text' => $this->texts->get('msg.receipt.not_pending', 'Not pending'),
                    'show_alert' => true,
                ]);

                return;
            }
            $markup = $this->keyboards->inlineReceiptRejectReasons($rid);
            if ($msgId > 0) {
                $this->runtime->editMessageReplyMarkup($ctx, [
                    'chat_id' => $chatId,
                    'message_id' => $msgId,
                    'reply_markup' => $markup,
                ]);
            } else {
                $this->receiptReview->editAdminMessages($ctx, $rec, $markup);
            }
            $this->runtime->answerCallbackQuery($ctx, [
                'callback_query_id' => $cbId,
                'text' => $this->texts->get('msg.receipt.reject_pick_reason', 'Pick reason'),
            ]);
        }
    }

    /** @param  array<string, mixed>  $from */
    public function handleAdminText(BotContext $ctx, SvpUser $user, int $chatId, string $text, array $from): void
    {
        $this->adminRegistry->handleAdminReplyText($ctx, $user, $chatId, $text, $from);
    }
}
