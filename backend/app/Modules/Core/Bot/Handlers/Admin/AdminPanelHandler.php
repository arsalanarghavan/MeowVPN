<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminNavService;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Services\Portal\PortalDashboardStatsService;
use App\Modules\Core\Services\Portal\PortalLinkService;

class AdminPanelHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminNavService $nav,
        protected AdminPermissionService $permissions,
        protected AdminKeyboardBuilder $keyboards,
        protected AdminBotScopeService $scope,
        protected BotStateService $state,
        protected PortalDashboardStatsService $stats,
        protected PortalLinkService $portal,
        protected AdminUsersHandler $users,
        protected AdminReceiptsHandler $receipts,
        protected AdminCatalogHandler $catalog,
        protected AdminFinanceHandler $finance,
        protected AdminMarketingHandler $marketing,
        protected AdminResellersHandler $resellers,
        protected AdminBulkHandler $bulk,
        protected AdminSettingsHandler $settings,
        protected AdminRelayHandler $relay,
        protected AdminStatsHandler $statsHandler,
        protected AdminEconomicsHandler $economics,
        protected AdminInboundHandler $inbound,
        protected AdminBackupHandler $backup,
        protected AdminLogsHandler $logs,
        protected AdminTextsHandler $textsHandler,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.panel', $user, '⬅️ Panel');
    }

    public function sendPanelEntry(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $this->scope->setActingAdmin((int) $user->id);
        $this->state->clear($user);
        $user->admin_mode = true;
        $user->save();

        $body = $this->landingText($user);
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->panelMainReply($user),
        ]);
    }

    public function landingText(SvpUser $user): string
    {
        $intro = $this->texts->getForUser('msg.admin.panel_welcome', $user);
        $resellerId = $this->permissions->permissionActorId($user);
        $payload = $this->stats->buildPayload(0, $resellerId);
        if (! empty($payload['text'])) {
            $intro .= "\n\n".trim((string) $payload['text']);
        }
        $roleKey = $resellerId > 0 ? 'msg.admin.panel.role_reseller' : 'msg.admin.panel.role_site_admin';
        $intro .= "\n\n".$this->texts->getForUser($roleKey, $user);

        return $intro;
    }

    public function handleNavText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $this->scope->setActingAdmin((int) $user->id);
        $allowed = $this->permissions->allowedTabs($user);
        $text = trim($text);

        if ($text === $this->texts->getForUser('btn.admin.back_panel', $user, '⬅️ Panel')
            || $text === $this->navLabel($user)) {
            $this->sendPanelEntry($ctx, $chatId, $user);

            return true;
        }

        $portalBtn = $this->texts->getForUser('btn.admin.send_admin_portal', $user, '');
        if ($portalBtn !== '' && $text === $portalBtn) {
            $link = $this->portal->buildAdminLink((int) $user->id, 3600);
            $this->send($ctx, $chatId, $link);

            return true;
        }

        if ($sec = $this->nav->matchSectionFromText($text, $user, $allowed)) {
            $this->sendSection($ctx, $chatId, $user, $sec);

            return true;
        }

        if ($tab = $this->nav->matchTabFromText($text, $user, $allowed)) {
            $this->openTab($ctx, $chatId, $user, $tab);

            return true;
        }

        return false;
    }

    public function sendSection(BotContext $ctx, int $chatId, SvpUser $user, string $sectionId): void
    {
        $allowed = $this->permissions->allowedTabs($user);
        $tabs = $this->nav->tabsInSection($sectionId, $user, $allowed);
        if ($tabs === []) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_tab', $user), [
                'reply_markup' => $this->keyboards->panelMainReply($user),
            ]);

            return;
        }

        $key = $this->nav->introKey('section', $sectionId);
        $body = $this->texts->getForUser($key, $user, $sectionId);
        $this->state->set($user, 'admin_panel_section', ['section' => $sectionId]);
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->sectionReply($sectionId, $user),
        ]);
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        $tabKey = strtolower(trim($tabKey));
        if ($tabKey === '') {
            return;
        }

        if (! $this->permissions->mayAccessTab($user, $tabKey)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user), [
                'reply_markup' => $this->keyboards->panelMainReply($user),
            ]);

            return;
        }

        $sec = $this->nav->sectionForTab($tabKey);
        if ($sec !== '') {
            $this->state->set($user, 'admin_panel_section', ['section' => $sec, 'tab' => $tabKey]);
        }

        match ($tabKey) {
            'users' => $this->users->openUsersSubmenu($ctx, $chatId, $user),
            'users_bulk' => $this->bulk->openTab($ctx, $chatId, $user),
            'broadcast' => $this->marketing->openBroadcastPrompt($ctx, $chatId, $user),
            'receipts' => $this->receipts->sendPendingReviewPaged($ctx, $user, $chatId, 0),
            'plans', 'cards', 'plan_cats' => $this->catalog->sendList($ctx, $chatId, $user, $tabKey, 0),
            'referral', 'marketing_lifecycle', 'discounts' => $this->marketing->openTab($ctx, $chatId, $user, $tabKey),
            'resellers', 'reseller_reports', 'reseller_bots', 'reseller_xui_panels' => $this->resellers->openTab($ctx, $chatId, $user, $tabKey),
            'referral_reports', 'reseller_charge', 'customer_charges' => $this->finance->openTab($ctx, $chatId, $user, $tabKey),
            'unit_economics' => $this->economics->openTab($ctx, $chatId, $user, 'unit_economics'),
            'monitoring' => $this->statsHandler->openMonitoring($ctx, $chatId, $user),
            'logs', 'audit' => $this->logs->openTab($ctx, $chatId, $user, $tabKey),
            'backup' => $this->backup->openTab($ctx, $chatId, $user),
            'texts' => $this->textsHandler->openTab($ctx, $chatId, $user),
            'bots', 'site_settings', 'notifications', 'bot_ui', 'proxy', 'force_join', 'purge_expired', 'finance' => $this->settings->openTab($ctx, $chatId, $user, $tabKey),
            'relay' => $this->relay->openTab($ctx, $chatId, $user, $tabKey),
            'xui_panels', 'configs', 'l2tp_servers', 'xray_core', 'xray_inbounds', 'xray_hosts', 'tunnel_nodes' => $this->inbound->openTab($ctx, $chatId, $user, $tabKey),
            'reseller_settings' => $this->resellers->openResellerSettings($ctx, $chatId, $user),
            default => $this->send($ctx, $chatId, $this->texts->getForUser($this->nav->introKey('tab', $tabKey), $user, $tabKey)),
        };
    }
}
