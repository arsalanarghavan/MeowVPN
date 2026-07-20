<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Handlers\Admin\AdminBackupHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminBulkHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminCatalogHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminEconomicsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminFinanceHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminInboundHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminMarketingHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminPlanInboundsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminRelayHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminSettingsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminTextsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminLogsHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminResellersHandler;
use App\Modules\Core\Bot\Handlers\Admin\AdminUsersHandler;
use App\Modules\Core\Bot\Handlers\WalletHandler;

class AdminStateRouter
{
    public function __construct(
        protected BotStateService $state,
        protected WalletHandler $wallet,
        protected AdminUsersHandler $users,
        protected AdminCatalogHandler $catalog,
        protected AdminMarketingHandler $marketing,
        protected AdminSettingsHandler $settings,
        protected AdminRelayHandler $relay,
        protected AdminBackupHandler $backup,
        protected AdminBulkHandler $bulk,
        protected AdminTextsHandler $textsHandler,
        protected AdminEconomicsHandler $economics,
        protected AdminFinanceHandler $finance,
        protected AdminInboundHandler $inbound,
        protected AdminResellersHandler $resellers,
        protected AdminLogsHandler $logs,
        protected AdminPlanInboundsHandler $planInbounds,
        protected TextService $texts,
        protected BotRuntime $runtime,
    ) {}

    /**
     * @param  array<string, mixed>  $update
     * @param  array<string, mixed>  $from
     */
    public function route(BotContext $ctx, SvpUser $user, int $chatId, ?string $text, array $update, array $from): bool
    {
        if (! empty($update['message']['document']) && is_array($update['message']['document'])) {
            if ($this->backup->handleDocument($ctx, $user, $chatId, $update['message'])) {
                return true;
            }
        }

        $state = $this->state->get($user);
        if ($state === '') {
            return false;
        }

        $trimmed = trim((string) $text);

        if ($state === 'wallet_topup_amount') {
            $this->wallet->handleTopupState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'admin_broadcast') {
            $this->marketing->handleBroadcastText($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_catalog_')) {
            if (in_array($state, [AdminPlanInboundsHandler::STATE_CREATE, AdminPlanInboundsHandler::STATE_EDIT], true)) {
                $this->planInbounds->routeText($ctx, $user, $chatId, $trimmed);

                return true;
            }
            $this->catalog->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === AdminPlanInboundsHandler::STATE_CREATE) {
            $this->planInbounds->routeText($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_settings_')
            || $state === 'admin_settings_tab_wizard'
            || str_starts_with($state, 'admin_wizard_')
            || in_array($state, [
                'admin_force_join_publish',
                'admin_purge_one',
                'admin_purge_ready',
                'admin_crypto_settings',
                'admin_receipt_reject_reasons',
            ], true)) {
            $this->settings->routeWizardText($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_relay_')) {
            $this->relay->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_users_')) {
            $this->users->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (in_array($state, [
            'admin_discount_code',
            'admin_discount_delete',
            'admin_discount_edit',
            'admin_discount_toggle',
            'admin_discount_redemptions',
            'admin_lifecycle_new',
            'admin_lifecycle_run',
            'admin_lifecycle_delete',
            'admin_lifecycle_edit',
            'admin_lifecycle_toggle',
            'admin_marketing_manual',
            'admin_referral_percent',
            'admin_broadcast_cancel',
        ], true)) {
            $this->marketing->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'admin_users_create_service') {
            if ($this->users->routeCreateServiceText($ctx, $user, $chatId, $trimmed)) {
                return true;
            }
        }

        if (in_array($state, ['admin_users_search', 'admin_users_moderation'], true)) {
            if ($this->users->routeModerationText($ctx, $user, $chatId, $trimmed, $from)) {
                return true;
            }
        }

        if (str_starts_with($state, 'admin_bulk_')) {
            $this->bulk->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_texts_')) {
            $this->textsHandler->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_economics_')) {
            $this->economics->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'admin_finance_reseller_charge' || str_starts_with($state, 'admin_reseller_charges_')) {
            $this->finance->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_inbound_')) {
            $this->inbound->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if (str_starts_with($state, 'admin_reseller_') || str_starts_with($state, 'admin_wholesale_')) {
            $this->resellers->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($state === 'admin_logs_clear') {
            $this->logs->routeState($ctx, $user, $chatId, $trimmed);

            return true;
        }

        if ($this->isCancelText($trimmed, $user)) {
            $this->state->clear($user);
            $this->runtime->sendMessage($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));

            return true;
        }

        return false;
    }

    protected function isCancelText(string $text, SvpUser $user): bool
    {
        if ($text === '') {
            return false;
        }
        $back = $this->texts->getForUser('btn.admin.back_panel', $user, '');
        if ($back !== '' && $text === $back) {
            return true;
        }

        return in_array(mb_strtolower($text), ['cancel', 'لغو', 'انصراف'], true);
    }
}
