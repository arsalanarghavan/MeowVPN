<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminNavService;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;

class AdminHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminPanelHandler $panel,
        protected AdminNavService $nav,
        protected AdminPermissionService $permissions,
        protected BotStateService $state,
        protected AdminUsersHandler $users,
        protected AdminReceiptsHandler $receipts,
        protected AdminCatalogHandler $catalog,
        protected AdminFinanceHandler $finance,
        protected AdminSettingsHandler $settings,
        protected AdminRelayHandler $relay,
        protected AdminStatsHandler $stats,
        protected AdminMarketingHandler $marketing,
        protected AdminEconomicsHandler $economics,
        protected AdminInboundHandler $inbound,
        protected AdminResellersHandler $resellers,
        protected AdminBulkHandler $bulk,
        protected AdminBackupHandler $backup,
        protected AdminLogsHandler $logs,
        protected AdminTextsHandler $textsHandler,
    ) {
        parent::__construct($runtime, $texts);
    }

    /** @param  array<string, mixed>  $from */
    public function routeReplyText(BotContext $ctx, SvpUser $user, int $chatId, string $text, array $from): void
    {
        $trimmed = trim($text);

        if ($this->isWizardCancel($trimmed, $user)) {
            $this->state->clear($user);
            $this->panel->sendPanelEntry($ctx, $chatId, $user);

            return;
        }

        if ($this->panel->handleNavText($ctx, $user, $chatId, $trimmed)) {
            return;
        }

        if ($route = $this->matchAdminRouteFromText($trimmed, $user)) {
            if ($this->dispatchAdminRoute($ctx, $user, $chatId, $route)) {
                return;
            }
        }

        if (preg_match('#^/(pan|tg|wtg|wbl|swtg|swbl)$#', $trimmed, $m)) {
            $this->settings->handleOp($ctx, $chatId, $user, $m[1]);

            return;
        }

        if ($this->users->routeModerationText($ctx, $user, $chatId, $trimmed, $from)) {
            return;
        }

        $textHandlers = [
            $this->marketing,
            $this->settings,
            $this->relay,
            $this->bulk,
            $this->backup,
            $this->finance,
            $this->economics,
            $this->inbound,
            $this->textsHandler,
            $this->resellers,
            $this->catalog,
            $this->logs,
        ];
        foreach ($textHandlers as $handler) {
            if (method_exists($handler, 'routeText') && $handler->routeText($ctx, $user, $chatId, $trimmed)) {
                return;
            }
        }

        $navHandlers = [
            $this->panel,
            $this->users,
            $this->receipts,
            $this->catalog,
            $this->finance,
            $this->settings,
            $this->stats,
            $this->marketing,
            $this->economics,
            $this->inbound,
            $this->resellers,
            $this->bulk,
            $this->backup,
            $this->logs,
            $this->textsHandler,
        ];

        foreach ($navHandlers as $handler) {
            if ($handler->matchesNavText($trimmed, $user) && $handler->handleNav($ctx, $user, $chatId, $trimmed)) {
                return;
            }
        }

        $allowed = $this->permissions->allowedTabs($user);
        if ($this->nav->isAdminNavText($trimmed, $user, $allowed)) {
            return;
        }

        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.unknown_cmd', $user, 'Unknown command'));
    }

    public function dispatchAdminRoute(BotContext $ctx, SvpUser $user, int $chatId, string $routeKey): bool
    {
        $routeKey = strtolower(preg_replace('/[^a-z0-9_]/', '', $routeKey) ?? '');
        if ($routeKey === '') {
            return false;
        }

        return match ($routeKey) {
            'dashboard' => $this->dispatchPanel($ctx, $user, $chatId),
            'users' => $this->dispatchSection($ctx, $user, $chatId, 'users'),
            'finance' => $this->dispatchSection($ctx, $user, $chatId, 'finance'),
            'settings', 'advanced' => $this->dispatchSection($ctx, $user, $chatId, 'settings'),
            'users_search' => $this->dispatchUsersSearch($ctx, $user, $chatId),
            'users_queue' => $this->dispatchUsersQueue($ctx, $user, $chatId),
            'broadcast' => $this->dispatchBroadcast($ctx, $user, $chatId),
            'receipts' => $this->dispatchReceipts($ctx, $user, $chatId),
            'backup' => $this->dispatchBackup($ctx, $user, $chatId),
            default => false,
        };
    }

    protected function matchAdminRouteFromText(string $text, SvpUser $user): string
    {
        $map = [
            $this->texts->getForUser('btn.admin.back_panel', $user, '⬅️ Panel') => 'dashboard',
            $this->texts->getForUser('btn.admin.users_search', $user, 'Search') => 'users_search',
            $this->texts->getForUser('btn.admin.users_pending', $user, 'Pending') => 'users_queue',
            $this->texts->getForUser('btn.admin.tab.broadcast', $user, 'Broadcast') => 'broadcast',
            $this->texts->getForUser('btn.admin.tab.receipts', $user, 'Receipts') => 'receipts',
            $this->texts->getForUser('btn.admin.tab.backup', $user, 'Backup') => 'backup',
        ];
        foreach ($map as $label => $route) {
            if ($label !== '' && $text === $label) {
                return $route;
            }
        }

        return '';
    }

    protected function dispatchPanel(BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        $this->panel->sendPanelEntry($ctx, $chatId, $user);

        return true;
    }

    protected function dispatchSection(BotContext $ctx, SvpUser $user, int $chatId, string $section): bool
    {
        $this->panel->sendSection($ctx, $chatId, $user, $section);

        return true;
    }

    protected function dispatchUsersSearch(BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        $this->state->set($user, 'admin_users_search', []);
        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_user_search', $user, 'Send user id'));

        return true;
    }

    protected function dispatchUsersQueue(BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        $this->users->openUsersSubmenu($ctx, $chatId, $user);

        return true;
    }

    protected function dispatchBroadcast(BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        $this->marketing->openBroadcastPrompt($ctx, $chatId, $user);

        return true;
    }

    protected function dispatchReceipts(BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        $this->receipts->sendPendingReviewPaged($ctx, $user, $chatId, 0);

        return true;
    }

    protected function dispatchBackup(BotContext $ctx, SvpUser $user, int $chatId): bool
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return true;
        }
        $this->backup->openTab($ctx, $chatId, $user);

        return true;
    }

    protected function isWizardCancel(string $text, SvpUser $user): bool
    {
        if ($text === '') {
            return false;
        }
        $back = $this->texts->getForUser('btn.admin.back_panel', $user, '');
        if ($back !== '' && $text === $back && $this->state->get($user) !== '') {
            return true;
        }

        return in_array(mb_strtolower($text), ['cancel', 'لغو', 'انصراف'], true) && $this->state->get($user) !== '';
    }
}
