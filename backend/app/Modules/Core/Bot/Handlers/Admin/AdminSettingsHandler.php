<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class AdminSettingsHandler extends AbstractAdminHandler
{
    /** @var array<string, string> */
    protected const SETTINGS_KEY_TAB = [
        'public_site_url' => 'general',
        'admin_notify_chat_id' => 'notifications',
        'telegram_http_proxy' => 'proxy',
        'telegram_proxy_host' => 'proxy',
        'telegram_proxy_port' => 'proxy',
        'telegram_proxy_user' => 'proxy',
        'telegram_proxy_password' => 'proxy',
        'force_join_enabled' => 'force_join',
        'force_join_channel_id' => 'force_join',
        'force_join_channel_url' => 'force_join',
        'force_join_prompt' => 'force_join',
        'force_join_telegram_enabled' => 'force_join',
        'force_join_telegram_chat_id' => 'force_join',
        'force_join_telegram_username' => 'force_join',
        'force_join_telegram_invite_link' => 'force_join',
        'force_join_telegram_prompt_text' => 'force_join',
        'force_join_telegram_announce_text' => 'force_join',
        'force_join_bale_enabled' => 'force_join',
        'force_join_bale_chat_id' => 'force_join',
        'force_join_bale_username' => 'force_join',
        'force_join_bale_invite_link' => 'force_join',
        'force_join_bale_prompt_text' => 'force_join',
        'force_join_bale_announce_text' => 'force_join',
        'force_join_cache_ttl_sec' => 'force_join',
        'force_join_negative_cache_ttl_sec' => 'force_join',
        'purge_expired_enabled' => 'purge_expired',
        'purge_expired_grace_days' => 'purge_expired',
        'purge_expired_notify_user' => 'purge_expired',
    ];

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected SettingsStore $settings,
        protected BotStateService $state,
        protected AdminPermissionService $permissions,
        protected BotAdminMutateService $mutate,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.settings', $user, '⚙️ Settings');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        if ($this->isGlobalSettingsTab($tabKey) && $this->permissions->blocksGlobalSettingsForResellerBot($ctx)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.reseller.global_settings_denied', $user, 'Global settings are not available on this bot.'));

            return;
        }

        match ($tabKey) {
            'bots' => $this->sendBotsStatus($ctx, $chatId, $user),
            'site_settings' => $this->sendSiteSettings($ctx, $chatId, $user),
            'whitelabel' => $this->sendWhitelabel($ctx, $chatId, $user),
            'service_naming' => $this->sendServiceNaming($ctx, $chatId, $user),
            'resellers_defaults', 'reseller_settings' => $this->sendResellersDefaults($ctx, $chatId, $user),
            'referral' => $this->sendReferralSettings($ctx, $chatId, $user),
            'plans_catalog', 'plan_cats' => $this->sendPlansCatalog($ctx, $chatId, $user),
            'cards' => $this->sendCardsSettings($ctx, $chatId, $user),
            'receipts' => $this->sendReceiptsSettings($ctx, $chatId, $user),
            'backup' => $this->sendBackupSettings($ctx, $chatId, $user),
            'logs' => $this->sendLogsSettings($ctx, $chatId, $user),
            'notifications' => $this->sendNotifications($ctx, $chatId, $user),
            'bot_ui' => $this->sendBotUi($ctx, $chatId, $user),
            'proxy' => $this->sendProxy($ctx, $chatId, $user),
            'force_join' => $this->sendForceJoin($ctx, $chatId, $user),
            'purge_expired' => $this->sendPurgeExpired($ctx, $chatId, $user),
            'finance' => $this->sendFinance($ctx, $chatId, $user),
            default => $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.tutorial.'.$tabKey, $user, $tabKey)),
        };
    }

    public function handleOp(BotContext $ctx, int $chatId, SvpUser $user, string $code): void
    {
        if ($this->denyGlobalSettings($ctx, $user, $code)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.reseller.global_settings_denied', $user, 'Global settings are not available on this bot.'));

            return;
        }
        $result = match ($code) {
            'pan' => $this->testPanel(),
            'tg' => $this->testTelegram($ctx),
            'wtg' => ['ok' => true, 'message' => $this->runtime->webhookUrl('telegram', (string) $this->settings->get('telegram_webhook_secret', 'secret'))],
            'wbl' => ['ok' => true, 'message' => $this->runtime->webhookUrl('bale', (string) $this->settings->get('bale_webhook_secret', 'secret'))],
            'swtg' => $this->mutate->applyForUser($user, 'bot_set_webhook', ['platform' => 'telegram']),
            'swbl' => $this->mutate->applyForUser($user, 'bot_set_webhook', ['platform' => 'bale']),
            default => ['ok' => false, 'message' => 'unknown'],
        };
        $msg = ! empty($result['ok'])
            ? $this->texts->getForUser('msg.admin.op_ok', $user, (string) ($result['message'] ?? 'OK'))
            : $this->texts->getForUser('msg.admin.op_fail', $user, (string) ($result['message'] ?? 'failed'));
        $this->send($ctx, $chatId, $msg);
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trimmed = trim($text);
        $siteUrlBtn = $this->texts->getForUser('btn.admin.edit_site_url', $user, 'Edit site URL');
        $notifBtn = $this->texts->getForUser('btn.admin.edit_notifications', $user, 'Edit notifications');
        if ($trimmed === $siteUrlBtn) {
            $this->startSettingsWizard($ctx, $chatId, $user, 'public_site_url', 'Send new site URL');

            return true;
        }
        if ($trimmed === $notifBtn) {
            $this->startSettingsWizard($ctx, $chatId, $user, 'admin_notify_chat_id', 'Send admin notify chat id');

            return true;
        }

        if ($this->permissions->permissionActorId($user) > 0) {
            return false;
        }

        $settingsButtons = [
            $this->texts->getForUser('btn.admin.edit_proxy', $user, 'Edit proxy URL') => [
                'wizard' => ['key' => 'telegram_http_proxy', 'prompt' => 'Send telegram HTTP proxy URL'],
            ],
            $this->texts->getForUser('btn.admin.proxy_test', $user, 'Test proxy') => [
                'mutate' => ['op' => 'telegram_proxy_test'],
            ],
            $this->texts->getForUser('btn.admin.force_join_publish', $user, 'Publish force join') => [
                'wizard' => ['state' => 'admin_force_join_publish', 'prompt' => 'Send channel message text (or - for default prompt)'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_tg_enabled', $user, 'Toggle TG force join') => [
                'wizard' => ['key' => 'force_join_telegram_enabled', 'prompt' => 'Send yes/no for Telegram force join'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_tg_chat', $user, 'Edit TG force join chat id') => [
                'wizard' => ['key' => 'force_join_telegram_chat_id', 'prompt' => 'Send Telegram force join channel chat id'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_tg_username', $user, 'Edit TG force join username') => [
                'wizard' => ['key' => 'force_join_telegram_username', 'prompt' => 'Send Telegram force join @username'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_tg_invite', $user, 'Edit TG force join invite') => [
                'wizard' => ['key' => 'force_join_telegram_invite_link', 'prompt' => 'Send Telegram force join invite link'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_tg_prompt', $user, 'Edit TG force join prompt') => [
                'wizard' => ['key' => 'force_join_telegram_prompt_text', 'prompt' => 'Send Telegram force join prompt text'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_tg_announce', $user, 'Edit TG force join announce') => [
                'wizard' => ['key' => 'force_join_telegram_announce_text', 'prompt' => 'Send Telegram force join announce text'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_bale_enabled', $user, 'Toggle Bale force join') => [
                'wizard' => ['key' => 'force_join_bale_enabled', 'prompt' => 'Send yes/no for Bale force join'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_bale_chat', $user, 'Edit Bale force join chat id') => [
                'wizard' => ['key' => 'force_join_bale_chat_id', 'prompt' => 'Send Bale force join channel chat id'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_bale_username', $user, 'Edit Bale force join username') => [
                'wizard' => ['key' => 'force_join_bale_username', 'prompt' => 'Send Bale force join @username'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_bale_invite', $user, 'Edit Bale force join invite') => [
                'wizard' => ['key' => 'force_join_bale_invite_link', 'prompt' => 'Send Bale force join invite link'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_bale_prompt', $user, 'Edit Bale force join prompt') => [
                'wizard' => ['key' => 'force_join_bale_prompt_text', 'prompt' => 'Send Bale force join prompt text'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_bale_announce', $user, 'Edit Bale force join announce') => [
                'wizard' => ['key' => 'force_join_bale_announce_text', 'prompt' => 'Send Bale force join announce text'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_cache_ttl', $user, 'Edit force join cache TTL') => [
                'wizard' => ['key' => 'force_join_cache_ttl_sec', 'prompt' => 'Send positive cache TTL seconds (min 30)'],
            ],
            $this->texts->getForUser('btn.admin.edit_force_join_neg_cache_ttl', $user, 'Edit force join negative cache TTL') => [
                'wizard' => ['key' => 'force_join_negative_cache_ttl_sec', 'prompt' => 'Send negative cache TTL seconds (min 10)'],
            ],
            $this->texts->getForUser('btn.admin.force_join_publish', $user, 'Publish force join') => [
                'wizard' => ['state' => 'admin_force_join_publish', 'prompt' => 'Send channel message text (or - for default prompt)'],
            ],
            $this->texts->getForUser('btn.admin.purge_run_cron', $user, 'Run purge cron') => [
                'mutate' => ['op' => 'purge_expired_run_cron'],
            ],
            $this->texts->getForUser('btn.admin.purge_ready', $user, 'Purge ready batch') => [
                'wizard' => ['state' => 'admin_purge_ready', 'prompt' => 'Reply yes to purge ready services'],
            ],
            $this->texts->getForUser('btn.admin.purge_one', $user, 'Purge one service') => [
                'wizard' => ['state' => 'admin_purge_one', 'prompt' => 'Send service_id to purge'],
            ],
            $this->texts->getForUser('btn.admin.edit_purge_grace', $user, 'Edit purge grace days') => [
                'wizard' => ['key' => 'purge_expired_grace_days', 'prompt' => 'Send purge grace days'],
            ],
            $this->texts->getForUser('btn.admin.crypto_settings', $user, 'Save crypto settings') => [
                'wizard' => ['state' => 'admin_crypto_settings', 'prompt' => 'Send crypto settings JSON'],
            ],
            $this->texts->getForUser('btn.admin.receipt_reject_reasons', $user, 'Save reject reasons') => [
                'wizard' => ['state' => 'admin_receipt_reject_reasons', 'prompt' => 'Send reject reasons comma-separated'],
            ],
        ];
        foreach ($settingsButtons as $btn => $cfg) {
            if ($trimmed !== $btn) {
                continue;
            }
            if (isset($cfg['wizard'])) {
                $wiz = $cfg['wizard'];
                if (isset($wiz['key'])) {
                    $this->startSettingsWizard($ctx, $chatId, $user, (string) $wiz['key'], (string) $wiz['prompt']);
                } else {
                    $this->state->set($user, (string) $wiz['state'], []);
                    $this->send($ctx, $chatId, (string) $wiz['prompt']);
                }

                return true;
            }
            if (isset($cfg['mutate'])) {
                $result = $this->mutate->applyForUser($user, (string) $cfg['mutate']['op'], is_array($cfg['mutate']['params'] ?? null) ? $cfg['mutate']['params'] : []);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return true;
        }

        $tabWizards = [
            $this->texts->getForUser('btn.admin.edit_brand_name', $user, 'Edit brand name') => ['whitelabel', 'brand_name', 'Send brand name'],
            $this->texts->getForUser('btn.admin.edit_logo_url', $user, 'Edit logo URL') => ['whitelabel', 'logo_url', 'Send logo URL'],
            $this->texts->getForUser('btn.admin.edit_primary_color', $user, 'Edit primary color') => ['whitelabel', 'primary_color', 'Send primary color hex'],
            $this->texts->getForUser('btn.admin.edit_naming_pattern', $user, 'Edit naming pattern') => ['service_naming', 'pattern', 'Send naming pattern'],
            $this->texts->getForUser('btn.admin.edit_default_commission', $user, 'Edit default commission') => ['resellers_defaults', 'default_commission_percent', 'Send commission percent'],
            $this->texts->getForUser('btn.admin.edit_reseller_payment_methods', $user, 'Edit payment methods') => ['resellers_defaults', 'payment_methods', 'Send payment methods JSON'],
            $this->texts->getForUser('btn.admin.edit_referral_percent', $user, 'Edit referral percent') => ['referral', 'referral_percent', 'Send referral percent'],
            $this->texts->getForUser('btn.admin.toggle_referral', $user, 'Toggle referral') => ['referral', 'referral_enabled', 'Send yes/no'],
            $this->texts->getForUser('btn.admin.edit_plans_catalog', $user, 'Edit catalog defaults') => ['plans_catalog', 'default_visible', 'Send yes/no'],
            $this->texts->getForUser('btn.admin.edit_card_display_order', $user, 'Edit display order hint') => ['cards', 'display_order_hint', 'Send order hint'],
            $this->texts->getForUser('btn.admin.edit_backup_interval', $user, 'Edit backup interval') => ['backup', 'backup_interval_minutes', 'Send interval minutes'],
            $this->texts->getForUser('btn.admin.edit_backup_retention', $user, 'Edit retention days') => ['backup', 'backup_retention_days', 'Send retention days'],
            $this->texts->getForUser('btn.admin.edit_logs_retention', $user, 'Edit logs retention') => ['logs', 'logs_retention_days', 'Send retention days'],
        ];
        foreach ($tabWizards as $btn => [$tab, $field, $prompt]) {
            if ($trimmed === $btn) {
                $this->startTabFieldWizard($ctx, $chatId, $user, $tab, $field, $prompt);

                return true;
            }
        }

        if ($trimmed === $this->texts->getForUser('btn.admin.naming_reset', $user, 'Reset naming counter')) {
            $result = $this->mutate->applyForUser($user, 'settings_tab', [
                'tab' => 'service_naming',
                'service_naming_mode' => 'legacy',
                'service_naming_prefix' => '',
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        if ($trimmed === $this->texts->getForUser('btn.admin.logs_clear', $user, 'Clear logs')) {
            $this->state->set($user, 'admin_logs_clear', ['step' => 'confirm']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_logs_clear', $user, 'Reply yes to clear logs'));

            return true;
        }

        if ($trimmed === $this->texts->getForUser('btn.admin.receipt_reject_reasons', $user, 'Save reject reasons')) {
            $this->state->set($user, 'admin_receipt_reject_reasons', []);
            $this->send($ctx, $chatId, 'Send reject reasons comma-separated');

            return true;
        }

        $botButtons = [
            $this->texts->getForUser('btn.admin.bot_toggle', $user, 'Toggle bot') => ['bot_toggle_enabled', []],
            $this->texts->getForUser('btn.admin.bot_toggle_tg', $user, 'Toggle Telegram') => ['bot_toggle_platform_enabled', ['platform' => 'telegram']],
            $this->texts->getForUser('btn.admin.bot_toggle_bale', $user, 'Toggle Bale') => ['bot_toggle_platform_enabled', ['platform' => 'bale']],
            $this->texts->getForUser('btn.admin.bot_test_tg', $user, 'Test Telegram') => ['bot_test_telegram', []],
            $this->texts->getForUser('btn.admin.bot_test_bale', $user, 'Test Bale') => ['bot_test_bale', []],
            $this->texts->getForUser('btn.admin.bot_diagnostics_tg', $user, 'Diagnostics TG') => ['bot_diagnostics', ['platform' => 'telegram']],
            $this->texts->getForUser('btn.admin.bot_diagnostics_bale', $user, 'Diagnostics Bale') => ['bot_diagnostics', ['platform' => 'bale']],
            $this->texts->getForUser('btn.admin.bot_delete_webhook_tg', $user, 'Delete WH TG') => ['bot_delete_webhook', ['platform' => 'telegram']],
            $this->texts->getForUser('btn.admin.bot_delete_webhook_bale', $user, 'Delete WH Bale') => ['bot_delete_webhook', ['platform' => 'bale']],
            $this->texts->getForUser('btn.admin.bot_ui_reset', $user, 'Reset UI layout') => ['bot_ui_layout_reset', []],
        ];
        foreach ($botButtons as $btn => [$op, $params]) {
            if ($trimmed === $btn) {
                $result = $this->mutate->applyForUser($user, $op, $params);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

                return true;
            }
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.bot_admin_add', $user, 'Add admin id')) {
            $this->state->set($user, 'admin_wizard_bot_admin_add', ['step' => 'platform']);
            $this->send($ctx, $chatId, 'Send platform: telegram or bale');

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.bot_admin_remove', $user, 'Remove admin id')) {
            $this->state->set($user, 'admin_wizard_bot_admin_remove', ['step' => 'platform']);
            $this->send($ctx, $chatId, 'Send platform: telegram or bale');

            return true;
        }

        return false;
    }

    public function routeWizardText(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'admin_settings_tab_wizard') {
            $tab = $this->mutateTabKey((string) ($data['tab'] ?? ''));
            $field = (string) ($data['field'] ?? '');
            if ($tab !== '' && $field !== '' && $trimmed !== '') {
                $result = $this->mutate->applyForUser($user, 'settings_tab', [
                    'tab' => $tab,
                    $field => $this->castSettingValue($field, $trimmed),
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

                return;
            }
        }

        if (str_starts_with($state, 'admin_settings_')) {
            $key = (string) ($data['key'] ?? '');
            if ($key !== '' && $trimmed !== '') {
                if ($this->permissions->permissionActorId($user) > 0) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));
                    $this->state->clear($user);

                    return;
                }
                $tab = $this->mutateTabKey((string) ($data['tab'] ?? self::SETTINGS_KEY_TAB[$key] ?? 'general'));
                $result = $this->mutate->applyForUser($user, 'settings_tab', [
                    'tab' => $tab,
                    $key => $this->castSettingValue($key, $trimmed),
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

                return;
            }
        }

        if ($state === 'admin_force_join_publish') {
            $params = [];
            if ($trimmed !== '' && $trimmed !== '-') {
                $params['text'] = $trimmed;
            }
            $result = $this->mutate->applyForUser($user, 'force_join_publish', $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_purge_one' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'purge_expired_purge_one', ['service_id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_purge_ready' && in_array(mb_strtolower($trimmed), ['yes', 'y', 'بله'], true)) {
            $result = $this->mutate->applyForUser($user, 'purge_expired_purge_ready', ['confirm' => true]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_crypto_settings' && $trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $params = is_array($decoded) ? $decoded : [];
            $result = $this->mutate->applyForUser($user, 'crypto_settings', $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_receipt_reject_reasons' && $trimmed !== '') {
            $reasons = array_values(array_filter(array_map('trim', explode(',', $trimmed))));
            $result = $this->mutate->applyForUser($user, 'receipt_reject_reasons_save', ['reasons' => $reasons]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if (str_starts_with($state, 'admin_wizard_bot_ui')) {
            $result = $this->mutate->applyForUser($user, 'bot_ui_layout_save', ['layout' => ['raw' => $trimmed]]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_wizard_bot_admin_add' || $state === 'admin_wizard_bot_admin_remove') {
            $step = (string) ($data['step'] ?? '');
            $remove = $state === 'admin_wizard_bot_admin_remove';
            if ($step === 'platform' && in_array(mb_strtolower($trimmed), ['telegram', 'tg', 'bale', 'bl'], true)) {
                $plat = in_array(mb_strtolower($trimmed), ['bale', 'bl'], true) ? 'bale' : 'telegram';
                $this->state->set($user, $state, ['step' => 'id', 'platform' => $plat]);
                $this->send($ctx, $chatId, 'Send admin chat id');

                return;
            }
            if ($step === 'id' && is_numeric($trimmed)) {
                $op = $remove ? 'bot_admin_id_remove' : 'bot_admin_id_add';
                $result = $this->mutate->applyForUser($user, $op, [
                    'platform' => (string) ($data['platform'] ?? 'telegram'),
                    'admin_id' => (int) $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }
    }

    public function startSettingsWizard(BotContext $ctx, int $chatId, SvpUser $user, string $key, string $prompt): void
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $tab = self::SETTINGS_KEY_TAB[$key] ?? 'general';
        $this->state->set($user, 'admin_settings_'.$key, ['key' => $key, 'tab' => $tab]);
        $this->send($ctx, $chatId, $prompt);
    }

    protected function sendBotsStatus(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $tg = trim((string) $this->settings->get('telegram_bot_token', '')) !== '' ? '✅' : '❌';
        $bl = trim((string) $this->settings->get('bale_token', '')) !== '' ? '✅' : '❌';
        $enabled = ! empty($this->settings->get('bot_enabled', true)) ? '✅' : '⏸';
        $body = "Bots {$enabled}\nTelegram: {$tg}\nBale: {$bl}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n/pan /tg /wtg /wbl\n/swtg /swbl — set webhooks";
            $body .= "\n\n".$this->texts->getForUser('btn.admin.bot_toggle', $user, 'Toggle bot');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_toggle_tg', $user, 'Toggle Telegram');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_toggle_bale', $user, 'Toggle Bale');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_test_tg', $user, 'Test Telegram');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_test_bale', $user, 'Test Bale');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_diagnostics_tg', $user, 'Diagnostics TG');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_diagnostics_bale', $user, 'Diagnostics Bale');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_delete_webhook_tg', $user, 'Delete WH TG');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_delete_webhook_bale', $user, 'Delete WH Bale');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_admin_add', $user, 'Add admin id');
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_admin_remove', $user, 'Remove admin id');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendSiteSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $url = (string) $this->settings->get('public_site_url', config('app.url'));
        $body = "Site\n{$url}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_site_url', $user, 'Edit site URL');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendNotifications(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $chat = (string) $this->settings->get('admin_notify_chat_id', '—');
        $body = $this->texts->getForUser('msg.admin.tutorial.notifications', $user, 'Notifications')."\nChat: {$chat}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_notifications', $user, 'Edit notifications');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendBotUi(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $layout = $this->settings->get('bot_ui_layout', []);
        $n = is_array($layout) ? count($layout) : 0;
        $body = "Bot UI layouts: {$n}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $this->state->set($user, 'admin_wizard_bot_ui', []);
            $body .= "\n\nSend layout JSON to update";
            $body .= "\n".$this->texts->getForUser('btn.admin.bot_ui_reset', $user, 'Reset UI layout');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendProxy(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $proxy = (string) $this->settings->get('telegram_http_proxy', '—');
        $body = "Telegram proxy\n{$proxy}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_proxy', $user, 'Edit proxy URL');
            $body .= "\n".$this->texts->getForUser('btn.admin.proxy_test', $user, 'Test proxy');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendForceJoin(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $tgEnabled = ! empty($this->settings->get('force_join_telegram_enabled', false));
        $tgChat = (string) ($this->settings->get('force_join_telegram_chat_id', 0) ?: '—');
        $baleEnabled = ! empty($this->settings->get('force_join_bale_enabled', false));
        $baleChat = (string) ($this->settings->get('force_join_bale_chat_id', '—') ?: '—');
        $cacheTtl = (string) $this->settings->get('force_join_cache_ttl_sec', 180);
        $negTtl = (string) $this->settings->get('force_join_negative_cache_ttl_sec', 45);
        $tgMark = $tgEnabled ? '✅' : '❌';
        $baleMark = $baleEnabled ? '✅' : '❌';
        $body = "Force join\nTelegram {$tgMark} chat: {$tgChat}\nBale {$baleMark} chat: {$baleChat}\nCache TTL: {$cacheTtl}s / neg {$negTtl}s";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_force_join_tg_enabled', $user, 'Toggle TG force join');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_tg_chat', $user, 'Edit TG force join chat id');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_tg_username', $user, 'Edit TG force join username');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_tg_invite', $user, 'Edit TG force join invite');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_tg_prompt', $user, 'Edit TG force join prompt');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_tg_announce', $user, 'Edit TG force join announce');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_bale_enabled', $user, 'Toggle Bale force join');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_bale_chat', $user, 'Edit Bale force join chat id');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_bale_username', $user, 'Edit Bale force join username');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_bale_invite', $user, 'Edit Bale force join invite');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_bale_prompt', $user, 'Edit Bale force join prompt');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_bale_announce', $user, 'Edit Bale force join announce');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_cache_ttl', $user, 'Edit force join cache TTL');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_force_join_neg_cache_ttl', $user, 'Edit force join negative cache TTL');
            $body .= "\n".$this->texts->getForUser('btn.admin.force_join_publish', $user, 'Publish force join');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendPurgeExpired(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $enabled = ! empty($this->settings->get('purge_expired_enabled', false)) ? '✅' : '❌';
        $grace = (string) $this->settings->get('purge_expired_grace_days', '—');
        $body = "Purge expired {$enabled}\nGrace days: {$grace}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_purge_grace', $user, 'Edit purge grace days');
            $body .= "\n".$this->texts->getForUser('btn.admin.purge_run_cron', $user, 'Run purge cron');
            $body .= "\n".$this->texts->getForUser('btn.admin.purge_ready', $user, 'Purge ready batch');
            $body .= "\n".$this->texts->getForUser('btn.admin.purge_one', $user, 'Purge one service');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendFinance(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $crypto = ! empty($this->settings->get('crypto_enabled', false)) ? '✅' : '❌';
        $reasons = $this->settings->get('receipt_reject_reasons', []);
        $nReasons = is_array($reasons) ? count($reasons) : 0;
        $body = "Site finance\nCrypto: {$crypto}\nReject reasons: {$nReasons}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.crypto_settings', $user, 'Save crypto settings');
            $body .= "\n".$this->texts->getForUser('btn.admin.receipt_reject_reasons', $user, 'Save reject reasons');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function castSettingValue(string $key, string $value): mixed
    {
        if (str_contains($key, 'enabled') || str_contains($key, 'notify_user')) {
            $v = mb_strtolower(trim($value));

            return in_array($v, ['1', 'yes', 'y', 'true', 'on', 'بله'], true) ? true
                : (in_array($v, ['0', 'no', 'n', 'false', 'off', 'خیر'], true) ? false : $value);
        }
        if (str_ends_with($key, '_days') || str_ends_with($key, '_port') || str_ends_with($key, '_ttl_sec')
            || $key === 'admin_notify_chat_id'
            || $key === 'force_join_channel_id'
            || $key === 'force_join_telegram_chat_id'
            || $key === 'force_join_bale_chat_id') {
            return is_numeric($value) ? (int) $value : $value;
        }

        return $value;
    }

    protected function denyGlobalSettings(BotContext $ctx, SvpUser $user, string $code): bool
    {
        if ($this->permissions->blocksGlobalSettingsForResellerBot($ctx)) {
            return in_array($code, ['pan', 'tg', 'wtg', 'wbl', 'swtg', 'swbl'], true);
        }

        if ($this->permissions->permissionActorId($user) < 1) {
            return false;
        }

        return in_array($code, ['pan', 'wtg', 'wbl', 'swtg', 'swbl'], true);
    }

    protected function isGlobalSettingsTab(string $tabKey): bool
    {
        return in_array(strtolower(trim($tabKey)), [
            'bots', 'site_settings', 'notifications', 'bot_ui', 'proxy', 'force_join',
            'purge_expired', 'finance', 'backup', 'logs', 'whitelabel', 'service_naming',
            'resellers_defaults', 'referral', 'plans_catalog', 'cards', 'receipts',
        ], true);
    }

    /** @return array{ok: bool, message?: string} */
    protected function testPanel(): array
    {
        $panel = DB::table('svp_panels')->orderBy('sort_order')->first();
        if (! $panel) {
            return ['ok' => false, 'message' => 'no_panel'];
        }

        return ['ok' => true, 'message' => 'panel #'.(int) $panel->id];
    }

    /** @return array{ok: bool, message?: string} */
    protected function testTelegram(BotContext $ctx): array
    {
        $client = $this->runtime->client(new BotContext('telegram'));
        if (! $client) {
            return ['ok' => false, 'message' => 'no_token'];
        }
        $r = $client->getMe();

        return ['ok' => ! empty($r['ok']), 'message' => (string) ($r['result']['username'] ?? 'telegram')];
    }

    protected function sendWhitelabel(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $brand = (string) $this->settings->get('brand_name', '—');
        $body = "Whitelabel\nBrand: {$brand}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_brand_name', $user, 'Edit brand name');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_logo_url', $user, 'Edit logo URL');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_primary_color', $user, 'Edit primary color');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendServiceNaming(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $pattern = (string) $this->settings->get('service_naming_pattern', '—');
        $body = "Service naming\nPattern: {$pattern}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_naming_pattern', $user, 'Edit naming pattern');
            $body .= "\n".$this->texts->getForUser('btn.admin.naming_reset', $user, 'Reset naming counter');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendResellersDefaults(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $commission = (string) $this->settings->get('default_commission_percent', '—');
        $body = "Reseller defaults\nCommission: {$commission}%";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_default_commission', $user, 'Edit default commission');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_reseller_payment_methods', $user, 'Edit payment methods');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendReferralSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $enabled = ! empty($this->settings->get('referral_enabled', false)) ? '✅' : '❌';
        $percent = (string) $this->settings->get('referral_percent', '0');
        $body = "Referral {$enabled}\nPercent: {$percent}%";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_referral_percent', $user, 'Edit referral percent');
            $body .= "\n".$this->texts->getForUser('btn.admin.toggle_referral', $user, 'Toggle referral');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendPlansCatalog(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $visible = (string) $this->settings->get('plans_catalog_default_visible', '—');
        $body = "Plans catalog\nDefault visible: {$visible}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_plans_catalog', $user, 'Edit catalog defaults');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendCardsSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $count = (int) DB::table('svp_cards')->count();
        $body = "Cards\nCount: {$count}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.card_reorder', $user, 'Reorder cards');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_card_display_order', $user, 'Edit display order hint');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendReceiptsSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $reasons = $this->settings->get('receipt_reject_reasons', []);
        $n = is_array($reasons) ? count($reasons) : 0;
        $body = "Receipts\nReject reasons: {$n}";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.receipt_reject_reasons', $user, 'Save reject reasons');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendBackupSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $interval = (string) $this->settings->get('backup_interval_minutes', '60');
        $retention = (string) $this->settings->get('backup_retention_days', '—');
        $body = "Backup\nInterval: {$interval} min\nRetention: {$retention} days";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_backup_interval', $user, 'Edit backup interval');
            $body .= "\n".$this->texts->getForUser('btn.admin.edit_backup_retention', $user, 'Edit retention days');
            $body .= "\n".$this->texts->getForUser('btn.admin.backup_run', $user, 'Run backup');
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendLogsSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $days = (string) $this->settings->get('logs_retention_days', '—');
        $body = "Logs\nRetention: {$days} days";
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n\n".$this->texts->getForUser('btn.admin.edit_logs_retention', $user, 'Edit logs retention');
            $body .= "\n".$this->texts->getForUser('btn.admin.logs_clear', $user, 'Clear logs');
        }
        $this->send($ctx, $chatId, $body);
    }

    public function startTabFieldWizard(BotContext $ctx, int $chatId, SvpUser $user, string $tab, string $field, string $prompt): void
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $this->state->set($user, 'admin_settings_tab_wizard', ['tab' => $this->mutateTabKey($tab), 'field' => $field]);
        $this->send($ctx, $chatId, $prompt);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return $this->texts->getForUser('msg.admin.section.settings.intro', $user, 'Settings');
    }

    protected function mutateTabKey(string $tab): string
    {
        return match ($tab) {
            'site_settings' => 'general',
            default => $tab,
        };
    }
}
