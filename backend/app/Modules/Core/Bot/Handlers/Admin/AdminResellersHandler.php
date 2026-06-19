<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpPlan;
use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminResellersHandler extends AbstractAdminHandler
{
    public const PAGE_SIZE = 10;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminPermissionService $permissions,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected ResellerScopeService $resellerScope,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.resellers', $user, '🏪 Resellers');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        if (in_array($tabKey, ['resellers', 'reseller_reports'], true)
            && ! $this->permissions->mayCallOp($user, 'reseller_list')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }

        match ($tabKey) {
            'resellers' => $this->sendResellersList($ctx, $chatId, $user, 0),
            'reseller_reports' => $this->sendResellerReports($ctx, $chatId, $user),
            'reseller_bots' => $this->sendResellerBots($ctx, $chatId, $user, 0),
            'reseller_xui_panels' => $this->sendResellerXuiPanels($ctx, $chatId, $user, 0),
            default => $this->send($ctx, $chatId, $this->sectionIntro($user)),
        };
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $action = (string) ($parts[2] ?? '');
        if ($action === 'p' && isset($parts[3])) {
            $this->sendResellersList($ctx, $chatId, $user, (int) $parts[3]);

            return;
        }
        if ($action === 'bp' && isset($parts[3])) {
            $this->sendResellerBots($ctx, $chatId, $user, (int) $parts[3]);

            return;
        }
        if ($action === 'xp' && isset($parts[3])) {
            $this->sendResellerXuiPanels($ctx, $chatId, $user, (int) $parts[3]);

            return;
        }
        if ($action === 't' && isset($parts[3])) {
            $profileId = (int) $parts[3];
            $result = $this->mutate->applyForUser($user, 'bot_reseller_toggle_enabled', ['id' => $profileId]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'wh' && isset($parts[3])) {
            $resellerId = (int) $parts[3];
            $result = $this->mutate->applyForUser($user, 'reseller_bot_webhook_set', ['reseller_svp_user_id' => $resellerId]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'whd' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'reseller_bot_webhook_delete', ['reseller_svp_user_id' => (int) $parts[3]]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'sr' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'bot_reseller_secret_rotate', ['reseller_svp_user_id' => (int) $parts[3]]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'rwh' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_set_webhook_reseller', [
                'reseller_svp_user_id' => (int) $parts[3],
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trimmed = trim($text);
        $tokensBtn = $this->texts->getForUser('btn.admin.reseller_bot_tokens', $user, 'Bot tokens');
        $webhookBtn = $this->texts->getForUser('btn.admin.reseller_bot_webhook', $user, 'Set webhook');
        $webhookDelBtn = $this->texts->getForUser('btn.admin.reseller_bot_webhook_delete', $user, 'Delete webhook');
        $secretBtn = $this->texts->getForUser('btn.admin.reseller_secret_rotate', $user, 'Rotate secret');
        $relayWhBtn = $this->texts->getForUser('btn.admin.reseller_relay_webhook', $user, 'Relay set webhook');
        $pricesBtn = $this->texts->getForUser('btn.admin.reseller_panel_prices', $user, 'Panel prices');
        $xuiAssignBtn = $this->texts->getForUser('btn.admin.reseller_xui_assign', $user, 'Assign panel price');

        if ($trimmed === $tokensBtn) {
            $this->state->set($user, 'admin_reseller_bot_tokens', ['step' => 'reseller_id']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

            return true;
        }
        if ($trimmed === $webhookBtn) {
            $this->state->set($user, 'admin_reseller_bot_webhook', ['step' => 'reseller_id']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

            return true;
        }
        if ($trimmed === $webhookDelBtn) {
            $this->state->set($user, 'admin_reseller_bot_webhook_delete', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

            return true;
        }
        if ($trimmed === $secretBtn) {
            $this->state->set($user, 'admin_reseller_secret_rotate', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

            return true;
        }
        if ($trimmed === $relayWhBtn) {
            $this->state->set($user, 'admin_reseller_relay_webhook', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

            return true;
        }
        if ($trimmed === $pricesBtn || $trimmed === $xuiAssignBtn) {
            $this->state->set($user, 'admin_reseller_panel_prices', ['step' => 'reseller_id']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

            return true;
        }

        $resellerOps = [
            $this->texts->getForUser('btn.admin.reseller_save', $user, 'Save reseller bot') => 'admin_reseller_save',
            $this->texts->getForUser('btn.admin.reseller_delete', $user, 'Delete reseller bot') => 'admin_reseller_delete',
            $this->texts->getForUser('btn.admin.reseller_dashboard_provision', $user, 'Dashboard provision') => 'admin_reseller_dashboard_provision',
            $this->texts->getForUser('btn.admin.reseller_bind_users', $user, 'Bind users') => 'admin_reseller_bind_users',
            $this->texts->getForUser('btn.admin.reseller_permissions', $user, 'Permissions') => 'admin_reseller_permissions',
            $this->texts->getForUser('btn.admin.wholesale_line_save', $user, 'Wholesale line') => 'admin_wholesale_line_save',
            $this->texts->getForUser('btn.admin.wholesale_line_delete', $user, 'Delete wholesale') => 'admin_wholesale_line_delete',
            $this->texts->getForUser('btn.admin.reseller_wholesale_assign', $user, 'Assign wholesale') => 'admin_reseller_wholesale_assign',
            $this->texts->getForUser('btn.admin.reseller_backfill', $user, 'Backfill') => 'admin_reseller_backfill',
            $this->texts->getForUser('btn.admin.reseller_inbound_labels', $user, 'Inbound labels') => 'admin_reseller_inbound_labels',
            $this->texts->getForUser('btn.admin.reseller_payment_methods', $user, 'Payment methods') => 'admin_reseller_payment_methods',
        ];
        foreach ($resellerOps as $btn => $state) {
            if ($trimmed === $btn) {
                if ($this->permissions->permissionActorId($user) > 0 && str_starts_with($state, 'admin_reseller_dashboard')) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                    return true;
                }
                $this->state->set($user, $state, ['step' => 'reseller_id']);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_reseller_id', $user, 'Send reseller svp_user_id'));

                return true;
            }
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $data = $this->state->data($user);
        $trimmed = trim($text);

        if ($state === 'admin_reseller_bot_tokens') {
            $step = (string) ($data['step'] ?? 'reseller_id');
            if ($step === 'reseller_id' && is_numeric($trimmed)) {
                $this->state->set($user, 'admin_reseller_bot_tokens', [
                    'step' => 'token',
                    'reseller_svp_user_id' => (int) $trimmed,
                ]);
                $this->send($ctx, $chatId, 'Send telegram_token');

                return;
            }
            if ($step === 'token' && $trimmed !== '') {
                $result = $this->mutate->applyForUser($user, 'reseller_bot_tokens_save', [
                    'reseller_svp_user_id' => (int) ($data['reseller_svp_user_id'] ?? 0),
                    'telegram_token' => $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_reseller_bot_webhook' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'reseller_bot_webhook_set', [
                'reseller_svp_user_id' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_bot_webhook_delete' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'reseller_bot_webhook_delete', [
                'reseller_svp_user_id' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_secret_rotate' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'bot_reseller_secret_rotate', [
                'reseller_svp_user_id' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_relay_webhook' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'telegram_relay_set_webhook_reseller', [
                'reseller_svp_user_id' => (int) $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_panel_prices') {
            $step = (string) ($data['step'] ?? 'reseller_id');
            if ($step === 'reseller_id' && is_numeric($trimmed)) {
                $this->state->set($user, 'admin_reseller_panel_prices', [
                    'step' => 'line',
                    'reseller_svp_user_id' => (int) $trimmed,
                ]);
                $this->send($ctx, $chatId, 'Send panel_id|price_per_gb');

                return;
            }
            if ($step === 'line' && str_contains($trimmed, '|')) {
                $parts = array_map('trim', explode('|', $trimmed));
                $panelId = $this->resolvePanelPricePanelId((int) ($parts[0] ?? 0));
                $pricePerGb = (float) str_replace(',', '.', (string) ($parts[1] ?? '0'));
                $result = $this->mutate->applyForUser($user, 'reseller_panel_prices_save', [
                    'reseller_svp_user_id' => (int) ($data['reseller_svp_user_id'] ?? 0),
                    'prices' => [
                        ['panel_id' => $panelId, 'price_per_gb' => $pricePerGb],
                    ],
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        $this->routeResellerWizardState($ctx, $user, $chatId, $state, $data, $trimmed);
    }

    /** @param  array<string, mixed>  $data */
    protected function routeResellerWizardState(BotContext $ctx, SvpUser $user, int $chatId, string $state, array $data, string $trimmed): void
    {
        $step = (string) ($data['step'] ?? 'reseller_id');
        $rid = (int) ($data['reseller_svp_user_id'] ?? 0);

        if ($step === 'reseller_id' && is_numeric($trimmed)) {
            $rid = (int) $trimmed;
            $next = match ($state) {
                'admin_reseller_save' => ['step' => 'token', 'prompt' => 'Send telegram_token|bale_token (optional second part)'],
                'admin_reseller_delete' => ['step' => 'confirm', 'prompt' => 'Reply yes to delete bot profile'],
                'admin_reseller_dashboard_provision' => ['step' => 'username', 'prompt' => 'Send dashboard username'],
                'admin_reseller_bind_users' => ['step' => 'user_ids', 'prompt' => 'Send user ids comma-separated'],
                'admin_reseller_permissions' => ['step' => 'json', 'prompt' => 'Send permissions JSON e.g. {"users.manage":true}'],
                'admin_wholesale_line_save' => ['step' => 'line', 'prompt' => 'Send panel_id|label|price'],
                'admin_wholesale_line_delete' => ['step' => 'line_id', 'prompt' => 'Send wholesale line id'],
                'admin_reseller_wholesale_assign' => ['step' => 'line_ids', 'prompt' => 'Send line ids comma-separated'],
                'admin_reseller_backfill' => ['step' => 'confirm', 'prompt' => 'Reply yes to run backfill'],
                'admin_reseller_inbound_labels' => ['step' => 'json', 'prompt' => 'Send labels JSON'],
                'admin_reseller_payment_methods' => ['step' => 'json', 'prompt' => 'Send payment methods JSON'],
                default => null,
            };
            if ($next === null) {
                return;
            }
            $this->state->set($user, $state, array_merge($data, ['step' => $next['step'], 'reseller_svp_user_id' => $rid]));
            $this->send($ctx, $chatId, $next['prompt']);

            return;
        }

        if ($state === 'admin_reseller_save' && $step === 'token') {
            $parts = array_map('trim', explode('|', $trimmed));
            $params = ['reseller_svp_user_id' => $rid, 'telegram_token' => (string) ($parts[0] ?? '')];
            if (isset($parts[1]) && $parts[1] !== '') {
                $params['bale_token'] = $parts[1];
            }
            $result = $this->mutate->applyForUser($user, 'bot_reseller_save', $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_delete' && $step === 'confirm' && in_array(mb_strtolower($trimmed), ['yes', 'y', 'بله'], true)) {
            $result = $this->mutate->applyForUser($user, 'bot_reseller_delete', ['reseller_svp_user_id' => $rid]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_dashboard_provision' && $step === 'username' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'reseller_dashboard_provision', [
                'reseller_svp_user_id' => $rid,
                'username' => $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_bind_users' && $step === 'user_ids' && $trimmed !== '') {
            $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $trimmed) ?: [])));
            $result = $this->mutate->applyForUser($user, 'reseller_bind_users', [
                'reseller_svp_user_id' => $rid,
                'user_ids' => $ids,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_permissions' && $step === 'json' && $trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $result = $this->mutate->applyForUser($user, 'reseller_permissions_save', [
                'svp_user_id' => $rid,
                'permissions' => is_array($decoded) ? $decoded : [],
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_wholesale_line_save' && $step === 'line' && str_contains($trimmed, '|')) {
            $parts = array_map('trim', explode('|', $trimmed));
            $result = $this->mutate->applyForUser($user, 'wholesale_line_save', [
                'panel_id' => (int) ($parts[0] ?? 0),
                'label' => (string) ($parts[1] ?? ''),
                'price' => (float) str_replace(',', '.', (string) ($parts[2] ?? '0')),
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_wholesale_line_delete' && $step === 'line_id' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'wholesale_line_delete', ['id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_wholesale_assign' && $step === 'line_ids' && $trimmed !== '') {
            $ids = array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $trimmed) ?: [])));
            $result = $this->mutate->applyForUser($user, 'reseller_wholesale_lines_assign', [
                'reseller_svp_user_id' => $rid,
                'line_ids' => $ids,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_backfill' && $step === 'confirm' && in_array(mb_strtolower($trimmed), ['yes', 'y', 'بله'], true)) {
            $result = $this->mutate->applyForUser($user, 'reseller_backfill_run', ['reseller_svp_user_id' => $rid]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_inbound_labels' && $step === 'json' && $trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $result = $this->mutate->applyForUser($user, 'reseller_inbound_labels_save', [
                'reseller_svp_user_id' => $rid,
                'labels' => is_array($decoded) ? $decoded : [],
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_reseller_payment_methods' && $step === 'json' && $trimmed !== '') {
            $decoded = json_decode($trimmed, true);
            $result = $this->mutate->applyForUser($user, 'reseller_payment_methods_save', [
                'reseller_svp_user_id' => $rid,
                'methods' => is_array($decoded) ? $decoded : [],
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    public function openResellerSettings(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $actorId = $this->permissions->permissionActorId($user);
        if ($actorId < 1) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_tab', $user));

            return;
        }
        $dash = \App\Models\DashboardUser::query()->where('svp_user_id', $actorId)->first();
        $perms = is_array($dash?->permissions_json) ? $dash->permissions_json : [];
        $lines = [];
        foreach (['users.manage', 'plans.manage', 'receipts.review', 'services.manage', 'broadcast.send', 'users.bulk'] as $k) {
            $lines[] = '• '.$k.': '.(! empty($perms[$k]) ? '✅' : '❌');
        }
        $body = $this->texts->getForUser('msg.admin.tutorial.reseller_settings', $user, 'Reseller settings');
        $body .= "\n\n".implode("\n", $lines);
        $this->send($ctx, $chatId, $body);
    }

    protected function sendResellersList(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        $off = max(0, $offset);
        $total = (int) DB::table('svp_users')->where('role', 'reseller')->count();
        $rows = DB::table('svp_users')->where('role', 'reseller')->orderBy('id')->offset($off)->limit(self::PAGE_SIZE)->get();
        $body = $this->texts->getForUser('msg.admin.tutorial.resellers', $user, 'Resellers')." ({$total})\n";
        foreach ($rows as $r) {
            $body .= "\n• #".(int) $r->id.' '.(string) ($r->username ?? '');
        }
        if ($rows->isEmpty()) {
            $body .= "\n—";
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:res:p:'.max(0, $off - self::PAGE_SIZE)];
        }
        if ($total > $off + self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:res:p:'.($off + self::PAGE_SIZE)];
        }
        $ik = $nav !== [] ? [$nav] : [];
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function sendResellerReports(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $count = (int) DB::table('svp_users')->where('role', 'reseller')->count();
        $scoped = (int) DB::table('svp_users')->where('signup_reseller_svp_id', '>', 0)->count();
        $actorId = $this->permissions->permissionActorId($user);
        $downline = 0;
        if ($actorId > 0) {
            $downline = count($this->resellerScope->moderatableUserIds($actorId));
        }
        $body = "Reseller reports\nResellers: {$count}\nScoped users: {$scoped}";
        if ($actorId > 0) {
            $body .= "\nYour downline: {$downline}";
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendResellerBots(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        if (! Schema::hasTable('svp_reseller_bot_profiles')) {
            $this->send($ctx, $chatId, 'Reseller bots: 0');

            return;
        }
        $off = max(0, $offset);
        $total = (int) DB::table('svp_reseller_bot_profiles')->count();
        $rows = DB::table('svp_reseller_bot_profiles')->orderBy('id')->offset($off)->limit(self::PAGE_SIZE)->get();
        $body = "Reseller bots ({$total})\n";
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_bot_tokens', $user, 'Bot tokens');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_bot_webhook', $user, 'Set webhook');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_bot_webhook_delete', $user, 'Delete webhook');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_secret_rotate', $user, 'Rotate secret');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_relay_webhook', $user, 'Relay set webhook');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_save', $user, 'Save reseller bot');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_delete', $user, 'Delete reseller bot');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_permissions', $user, 'Permissions');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_bind_users', $user, 'Bind users');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_backfill', $user, 'Backfill');
        if ($this->permissions->permissionActorId($user) < 1) {
            $body .= "\n".$this->texts->getForUser('btn.admin.reseller_dashboard_provision', $user, 'Dashboard provision');
        }
        $ik = [];
        foreach ($rows as $r) {
            $pid = (int) $r->id;
            $rid = (int) ($r->reseller_svp_user_id ?? 0);
            $enabled = ! empty($r->enabled) ? '✅' : '⏸';
            $body .= "#{$pid} reseller {$rid} {$enabled}\n";
            $ik[] = [
                ['text' => 'Toggle #'.$pid, 'callback_data' => 'pnl:res:t:'.$pid],
                ['text' => 'Webhook', 'callback_data' => 'pnl:res:wh:'.$rid],
            ];
            $ik[] = [
                ['text' => 'Del WH', 'callback_data' => 'pnl:res:whd:'.$rid],
                ['text' => 'Rotate', 'callback_data' => 'pnl:res:sr:'.$rid],
            ];
            $ik[] = [
                ['text' => 'Relay WH', 'callback_data' => 'pnl:res:rwh:'.$rid],
            ];
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:res:bp:'.max(0, $off - self::PAGE_SIZE)];
        }
        if ($total > $off + self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:res:bp:'.($off + self::PAGE_SIZE)];
        }
        if ($nav !== []) {
            $ik[] = $nav;
        }
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function sendResellerXuiPanels(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        if (! Schema::hasTable('svp_reseller_panel_prices')) {
            $this->send($ctx, $chatId, "Resellers with panel prices: 0\n\n".$this->texts->getForUser('btn.admin.reseller_panel_prices', $user, 'Panel prices'));

            return;
        }
        $off = max(0, $offset);
        $q = DB::table('svp_reseller_panel_prices')->orderBy('reseller_svp_user_id')->orderBy('panel_id');
        $total = (int) DB::table('svp_reseller_panel_prices')->count();
        $rows = $q->offset($off)->limit(self::PAGE_SIZE)->get();
        $body = "Reseller panel prices ({$total})\n";
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_panel_prices', $user, 'Panel prices');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_xui_assign', $user, 'Assign panel price');
        $body .= "\n".$this->texts->getForUser('btn.admin.wholesale_line_save', $user, 'Wholesale line');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_wholesale_assign', $user, 'Assign wholesale');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_inbound_labels', $user, 'Inbound labels');
        $body .= "\n".$this->texts->getForUser('btn.admin.reseller_payment_methods', $user, 'Payment methods');
        foreach ($rows as $r) {
            $body .= "\nreseller ".(int) ($r->reseller_svp_user_id ?? 0).' panel '.(int) ($r->panel_id ?? 0).' = '.number_format((float) ($r->price_per_gb ?? $r->price ?? 0));
        }
        if ($rows->isEmpty()) {
            $body .= "\n—";
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:res:xp:'.max(0, $off - self::PAGE_SIZE)];
        }
        if ($total > $off + self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:res:xp:'.($off + self::PAGE_SIZE)];
        }
        $ik = $nav !== [] ? [$nav] : [];
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function resolvePanelPricePanelId(int $id): int
    {
        if ($id < 1) {
            return 0;
        }
        if (Schema::hasTable('svp_panels') && DB::table('svp_panels')->where('id', $id)->exists()) {
            return $id;
        }
        $planPanelId = (int) (SvpPlan::query()->find($id)?->panel_id ?? 0);

        return $planPanelId > 0 ? $planPanelId : $id;
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $count = DB::table('svp_users')->where('role', 'reseller')->count();

        return "Resellers: {$count}";
    }
}
