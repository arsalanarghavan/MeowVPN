<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\TextService;

class AdminHandlerRegistry
{
    public function __construct(
        protected BotRuntime $runtime,
        protected TextService $texts,
        protected AdminPnlHandler $pnl,
        protected AdminPanelHandler $panel,
        protected AdminUsersHandler $users,
        protected AdminReceiptsHandler $receipts,
        protected AdminCatalogHandler $catalog,
        protected AdminFinanceHandler $finance,
        protected AdminSettingsHandler $settings,
        protected AdminRelayHandler $relay,
        protected AdminTextsHandler $textsHandler,
        protected AdminStatsHandler $stats,
        protected AdminMarketingHandler $marketing,
        protected AdminEconomicsHandler $economics,
        protected AdminInboundHandler $inbound,
        protected AdminResellersHandler $resellers,
        protected AdminBulkHandler $bulk,
        protected AdminBackupHandler $backup,
        protected AdminLogsHandler $logs,
        protected AdminHandler $admin,
    ) {}

    /** @param  array<int, string>  $parts */
    public function handlePnl(BotContext $ctx, array $parts, ?SvpUser $user, int $chatId, int $msgId, int $fromId): void
    {
        if (! $user) {
            return;
        }
        $sub = (string) ($parts[1] ?? '');
        if ($sub === 'cat') {
            $this->catalog->handleCallback($ctx, $parts, $user, $chatId, $msgId);

            return;
        }
        if ($sub === 'rcp' && ($parts[2] ?? '') === 'p') {
            $this->receipts->sendPendingReviewPaged($ctx, $user, $chatId, (int) ($parts[3] ?? 0));

            return;
        }
        if ($sub === 'usr' && isset($parts[2], $parts[3])) {
            $this->users->handleUserAction($ctx, $user, $chatId, (string) $parts[2], (int) $parts[3]);

            return;
        }
        if (in_array($sub, ['umsg', 'wbp', 'wbm', 'ui', 'cs', 'pq', 'aq', 'rq', 'pe', 'svc_del', 'ar', 'av', 'rv', 'ad', 'sd', 'asv', 'mem', 'urcp', 'nsx', 'nsm', 'nrr', 'nva', 'nus', 'rr'], true)
            || ($sub === 'svc' && isset($parts[2]))) {
            if ($sub === 'pe') {
                $parts[1] = 'pq';
            }
            $this->users->handleCallback($ctx, $parts, $user, $chatId, $msgId);

            return;
        }
        if ($sub === 'lg') {
            $this->logs->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'inb' && isset($parts[2])) {
            $this->inbound->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'txt' && isset($parts[2])) {
            $this->textsHandler->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'bc') {
            $this->marketing->handleBroadcastCallback($ctx, $user, $chatId, $parts);

            return;
        }
        if ($sub === 'bulk' && isset($parts[2])) {
            $this->bulk->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'res' && isset($parts[2])) {
            $this->resellers->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'stats') {
            $this->stats->handleStatsOffset($ctx, $user, $chatId, (int) ($parts[2] ?? 0));

            return;
        }
        if ($sub === 'dash') {
            $this->stats->openMonitoring($ctx, $chatId, $user);

            return;
        }
        if ($sub === 'th' && isset($parts[2])) {
            $this->textsHandler->handlePreviewCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'tv' && isset($parts[2])) {
            $this->textsHandler->handleEditCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'bk') {
            if (isset($parts[2])) {
                $this->backup->handleCallback($ctx, $parts, $user, $chatId);

                return;
            }
            $this->backup->openTab($ctx, $chatId, $user);

            return;
        }
        if ($sub === 'fin' && isset($parts[2])) {
            $this->finance->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if (in_array($sub, ['blk', 'ub'], true) && isset($parts[2])) {
            $this->users->handleUserAction($ctx, $user, $chatId, $sub === 'blk' ? 'blk' : 'unb', (int) $parts[2]);

            return;
        }
        if ($sub === 'il' && isset($parts[2])) {
            $this->inbound->handleIlCallback($ctx, $parts, $user, $chatId);

            return;
        }
        if ($sub === 'relay' && isset($parts[2])) {
            $this->relay->handleCallback($ctx, $parts, $user, $chatId);

            return;
        }
        $this->pnl->handle($ctx, $parts, $user, $chatId, $msgId, $fromId);
    }

    public function handleRegistration(BotContext $ctx, string $action, int $uid, array $from, int $chatId, string $cbId): void
    {
        $this->users->handleRegistration($ctx, $action, $uid, $from, $chatId, $cbId);
    }

    /** @param  array<string, mixed>  $from */
    public function handleAdminReplyText(BotContext $ctx, SvpUser $user, int $chatId, string $text, array $from): void
    {
        $this->admin->routeReplyText($ctx, $user, $chatId, $text, $from);
    }
}
