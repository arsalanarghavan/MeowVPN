<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WP SimpleVPBot_Receipt_Notify — deliver receipt photos to admins with rc:* keyboard.
 */
class ReceiptNotifyService
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected AdminGuard $adminGuard,
        protected AdminKeyboardBuilder $keyboards,
        protected AdminReceiptReviewService $review,
    ) {}

    /**
     * Persist platform file id + optional local path, then deliver to admins.
     *
     * @return array{ok:bool, delivered:int}
     */
    public function onUpload(BotContext $ctx, int $receiptId, string $fileId, ?string $localPath = null): array
    {
        $receiptId = max(0, $receiptId);
        $fileId = trim($fileId);
        if ($receiptId < 1 || $fileId === '' || ! Schema::hasTable('svp_receipts')) {
            return ['ok' => false, 'delivered' => 0];
        }

        $col = $ctx->platform === 'bale' ? 'bale_file_id' : 'tg_file_id';
        $update = [
            $col => $fileId,
            'status' => 'pending',
        ];
        if ($localPath && is_readable($localPath) && Schema::hasColumn('svp_receipts', 'stored_image_path')) {
            $update['stored_image_path'] = $localPath;
        }
        DB::table('svp_receipts')->where('id', $receiptId)->update($update);

        return $this->deliverToAdmins($ctx, $receiptId, $fileId);
    }

    /**
     * @return array{ok:bool, delivered:int}
     */
    public function deliverToAdmins(BotContext $ctx, int $receiptId, string $fileId = ''): array
    {
        $rec = DB::table('svp_receipts')->where('id', $receiptId)->first();
        if (! $rec) {
            return ['ok' => false, 'delivered' => 0];
        }

        $lockKey = 'svp_receipt_admin_notify_'.$receiptId;
        if (! Cache::add($lockKey, '1', 120)) {
            return ['ok' => true, 'delivered' => 0];
        }

        try {
            $user = SvpUser::query()->find((int) ($rec->user_id ?? 0));
            $tx = DB::table('svp_transactions')->where('id', (int) ($rec->transaction_id ?? 0))->first();
            if (! $user || ! $tx) {
                return ['ok' => false, 'delivered' => 0];
            }

            $platform = $ctx->platform;
            $fileId = trim($fileId) !== '' ? trim($fileId) : (
                $platform === 'bale'
                    ? (string) ($rec->bale_file_id ?? '')
                    : (string) ($rec->tg_file_id ?? '')
            );

            $adminChatIds = $this->adminChatIdsForContext($ctx);
            $delivered = 0;
            foreach ($adminChatIds as $adminChatId) {
                if ($adminChatId < 1) {
                    continue;
                }
                if ($this->alreadyDelivered($rec, $platform, $adminChatId)) {
                    continue;
                }
                $admin = $this->adminGuard->resolveAdminByPlatformId($platform, $adminChatId)
                    ?? SvpUser::query()->where($platform === 'bale' ? 'bale_user_id' : 'tg_user_id', $adminChatId)->first();
                if (! $admin) {
                    // Still notify chat id even without local svp user row.
                    $admin = new SvpUser;
                    $admin->id = 0;
                }
                $this->review->sendOnePendingReview($ctx, $admin, $adminChatId, $rec);
                $delivered++;
                usleep(80000);
            }

            return ['ok' => true, 'delivered' => $delivered];
        } finally {
            Cache::forget($lockKey);
        }
    }

    /**
     * Recovery cron: re-notify pending receipts that have a file id but no admin delivery yet.
     */
    public function recoverPending(int $limit = 25): int
    {
        if (! Schema::hasTable('svp_receipts')) {
            return 0;
        }
        $cutoff = now()->subSeconds(120);
        $rows = DB::table('svp_receipts')
            ->whereIn('status', ['pending', 'processing'])
            ->where('created_at', '<=', $cutoff)
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();

        $n = 0;
        foreach ($rows as $rec) {
            $tg = trim((string) ($rec->tg_file_id ?? ''));
            $bl = trim((string) ($rec->bale_file_id ?? ''));
            if ($tg === '' && $bl === '') {
                continue;
            }
            $raw = (string) ($rec->admin_messages_json ?? '[]');
            $msgs = json_decode($raw, true);
            if (is_array($msgs) && $msgs !== []) {
                continue;
            }
            $platform = $tg !== '' ? 'telegram' : 'bale';
            $fileId = $platform === 'telegram' ? $tg : $bl;
            $ctx = new BotContext($platform);
            if ($ctx->isResellerBot() === false && (int) ($rec->billing_reseller_svp_id ?? 0) > 0) {
                // keep main bot context; reseller scope resolved via AdminGuard lists
            }
            $r = $this->deliverToAdmins($ctx, (int) $rec->id, $fileId);
            if (! empty($r['delivered'])) {
                $n += (int) $r['delivered'];
            }
        }

        return $n;
    }

    /** @return list<int> */
    protected function adminChatIdsForContext(BotContext $ctx): array
    {
        if ($ctx->isResellerBot() && $ctx->resellerSvpUserId > 0) {
            $key = $ctx->platform === 'bale'
                ? 'reseller_admin_bale_ids.'.$ctx->resellerSvpUserId
                : 'reseller_admin_telegram_ids.'.$ctx->resellerSvpUserId;
            $ids = array_map('intval', (array) app(\App\Services\SettingsStore::class)->get($key, []));
            if ($ids !== []) {
                return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
            }
        }

        return $this->adminGuard->platformAdminIds($ctx->platform);
    }

    protected function alreadyDelivered(object $rec, string $platform, int $chatId): bool
    {
        $raw = (string) ($rec->admin_messages_json ?? '[]');
        $list = json_decode($raw, true);
        if (! is_array($list)) {
            return false;
        }
        foreach ($list as $m) {
            if (! is_array($m)) {
                continue;
            }
            if ((string) ($m['platform'] ?? '') === $platform && (int) ($m['chat_id'] ?? 0) === $chatId) {
                return true;
            }
        }

        return false;
    }
}
