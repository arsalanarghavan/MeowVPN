<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AdminReceiptReviewService
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected AdminPermissionService $permissions,
        protected AdminBotScopeService $scope,
        protected AdminKeyboardBuilder $keyboards,
    ) {}

    public function sendPendingReviewPaged(BotContext $ctx, SvpUser $admin, int $chatId, int $offset = 0): void
    {
        $this->scope->setActingAdmin((int) $admin->id);

        if (! $this->permissions->mayCallOp($admin, 'receipt_review')) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $admin));

            return;
        }

        $lockKey = 'svp_rcp_review_'.$ctx->platform.'_'.$chatId;
        if (! Cache::add($lockKey, '1', 55)) {
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.admin.receipt_review_busy', $admin, 'Review in progress'));

            return;
        }

        try {
            $per = max(1, min(10, 3));
            $off = max(0, $offset);
            $scopeIds = $this->scope->botAdminScopeUserIds($admin);

            $query = DB::table('svp_receipts')->where('status', 'pending');
            if (is_array($scopeIds)) {
                $query->whereIn('user_id', $scopeIds !== [] ? $scopeIds : [-1]);
            }
            $total = (int) $query->count();

            if ($total < 1) {
                $this->runtime->sendMessage(
                    $ctx,
                    $chatId,
                    $this->texts->getForUser('msg.admin.receipt_none', $admin, 'No pending receipts')
                );

                return;
            }

            $listQuery = DB::table('svp_receipts')->where('status', 'pending')->orderBy('id');
            if (is_array($scopeIds)) {
                $listQuery->whereIn('user_id', $scopeIds !== [] ? $scopeIds : [-1]);
            }
            $list = $listQuery->offset($off)->limit($per)->get();

            if ($list->isEmpty()) {
                $this->runtime->sendMessage(
                    $ctx,
                    $chatId,
                    $this->texts->getForUser('msg.admin.receipt_page_empty', $admin, 'Page empty')
                );

                return;
            }

            $this->runtime->sendMessage(
                $ctx,
                $chatId,
                $this->texts->getForUser('msg.admin.receipt_sending', $admin, 'Sending '.$list->count().' receipt(s)')
            );

            foreach ($list as $rec) {
                $this->sendOnePendingReview($ctx, $admin, $chatId, $rec);
                usleep(180000);
            }

            $next = $off + $list->count();
            $sum = '🧾 '.($off + 1).'–'.min($next, $total).' / '.$total;
            $extra = $next < $total
                ? ['reply_markup' => $this->keyboards->inlineReceiptPage($next)]
                : [];
            $this->runtime->sendMessage($ctx, $chatId, $sum, $extra);
        } finally {
            Cache::forget($lockKey);
        }
    }

    public function sendOnePendingReview(BotContext $ctx, SvpUser $admin, int $chatId, object $rec): void
    {
        $rid = (int) $rec->id;
        $ru = DB::table('svp_users')->where('id', (int) $rec->user_id)->first();
        $tx = DB::table('svp_transactions')->where('id', (int) $rec->transaction_id)->first();
        if (! $ru || ! $tx) {
            $this->runtime->sendMessage(
                $ctx,
                $chatId,
                $this->texts->getForUser('msg.admin.receipt_incomplete', $admin, "Receipt #{$rid} incomplete"),
                ['reply_markup' => $this->keyboards->inlineReceipt($rid)]
            );

            return;
        }

        $body = $this->buildCaption($ru, $tx, $rid, $rec);
        $markup = ['reply_markup' => $this->keyboards->inlineReceipt($rid)];
        $fileId = $ctx->platform === 'bale'
            ? (string) ($rec->bale_file_id ?? '')
            : (string) ($rec->tg_file_id ?? '');

        $sentMsgId = 0;
        if ($fileId !== '') {
            $client = $this->runtime->client($ctx);
            if ($client) {
                $r = $client->sendPhoto(array_merge([
                    'chat_id' => $chatId,
                    'photo' => $fileId,
                    'caption' => $body,
                    'parse_mode' => 'HTML',
                ], $markup));
                if (is_array($r) && ! empty($r['ok'])) {
                    $sentMsgId = (int) ($r['result']['message_id'] ?? 0);
                    $this->trackAdminMessage($rid, $ctx->platform, $chatId, $sentMsgId);

                    return;
                }
            }
        }

        $r = $this->runtime->sendMessage($ctx, $chatId, $body."\n\n(no photo)", $markup);
        if (is_array($r) && ! empty($r['ok'])) {
            $sentMsgId = (int) ($r['result']['message_id'] ?? 0);
            $this->trackAdminMessage($rid, $ctx->platform, $chatId, $sentMsgId);
        }
    }

    /**
     * @param  array<string, mixed>  $markup
     */
    public function editAdminMessages(BotContext $ctx, object $rec, array $markup): void
    {
        $rid = (int) $rec->id;
        $raw = (string) ($rec->admin_messages_json ?? '[]');
        $list = json_decode($raw, true);
        if (! is_array($list)) {
            $list = [];
        }
        foreach ($list as $m) {
            if (! is_array($m)) {
                continue;
            }
            $plat = (string) ($m['platform'] ?? $ctx->platform);
            $cid = (int) ($m['chat_id'] ?? 0);
            $mid = (int) ($m['message_id'] ?? 0);
            if ($cid < 1 || $mid < 1) {
                continue;
            }
            $platCtx = new BotContext($plat);
            $this->runtime->editMessageReplyMarkup($platCtx, [
                'chat_id' => $cid,
                'message_id' => $mid,
                'reply_markup' => $markup,
            ]);
        }
    }

    protected function trackAdminMessage(int $receiptId, string $platform, int $chatId, int $messageId): void
    {
        if ($messageId < 1) {
            return;
        }
        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if (! $rec) {
            return;
        }
        $raw = (string) ($rec->admin_messages_json ?? '[]');
        $list = json_decode($raw, true);
        if (! is_array($list)) {
            $list = [];
        }
        $list[] = [
            'platform' => $platform,
            'chat_id' => $chatId,
            'message_id' => $messageId,
        ];
        DB::table('svp_receipts')->where('id', $receiptId)->update([
            'admin_messages_json' => json_encode($list),
        ]);
    }

    protected function buildCaption(object $user, object $tx, int $rid, object $rec): string
    {
        $uname = (string) ($user->username ?? $user->tg_username ?? '#'.$user->id);
        $amount = number_format((float) $rec->amount, 0);
        $type = (string) ($tx->type ?? '');

        return "🧾 #{$rid}\n👤 {$uname}\n💰 {$amount}\n📋 {$type}";
    }
}
