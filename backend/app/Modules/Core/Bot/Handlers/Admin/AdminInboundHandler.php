<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminBotScopeService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Reseller\Services\ResellerScopeService;
use App\Modules\XuiPanel\Services\ConfigsSyncService;
use App\Modules\XuiPanel\Services\InboundMapService;
use App\Modules\XuiPanel\Services\PanelMaintenanceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminInboundHandler extends AbstractAdminHandler
{
    public const PAGE_SIZE = 8;

    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected InboundMapService $inboundMap,
        protected AdminBotScopeService $scope,
        protected ResellerScopeService $resellerScope,
        protected ConfigsSyncService $configsSync,
        protected PanelMaintenanceService $panelMaintenance,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.inbound', $user, '🔗 Inbounds');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        match ($tabKey) {
            'xui_panels' => $this->sendPanelList($ctx, $chatId, $user),
            'configs' => $this->sendConfigMap($ctx, $chatId, $user),
            'l2tp_servers' => $this->sendL2tpList($ctx, $chatId, $user, 0),
            default => $this->send($ctx, $chatId, $this->sectionIntro($user)),
        };
    }

    /** @param  array<int, string>  $parts */
    public function handleCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $action = (string) ($parts[2] ?? '');
        if ($action === 'pn' && isset($parts[3])) {
            $this->sendInboundsForPanel($ctx, $chatId, $user, (int) $parts[3]);

            return;
        }
        if ($action === 'ib' && isset($parts[3], $parts[4])) {
            $this->sendInboundClients($ctx, $chatId, $user, (int) $parts[3], (int) $parts[4]);

            return;
        }
        if ($action === 'al' && isset($parts[3], $parts[4])) {
            $result = $this->mutate->applyForUser($user, 'inbound_autolink', [
                'panel_id' => (int) $parts[3],
                'inbound_id' => (int) $parts[4],
            ]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'lk' && isset($parts[3], $parts[4], $parts[5])) {
            $clientId = (int) $parts[5];
            $email = '';
            if (Schema::hasTable('svp_panel_inbound_clients')) {
                $row = DB::table('svp_panel_inbound_clients')->where('id', $clientId)->first();
                $email = (string) ($row->email ?? '');
            }
            $this->state->set($user, 'admin_inbound_link', [
                'panel_id' => (int) $parts[3],
                'inbound_id' => (int) $parts[4],
                'email' => $email,
            ]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_inbound_service_id', $user, 'Send service_id to link'));

            return;
        }
        if ($action === 'l2p' && isset($parts[3])) {
            $this->sendL2tpList($ctx, $chatId, $user, (int) $parts[3]);

            return;
        }
        if ($action === 'l2d' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'l2tp_delete', ['id' => (int) $parts[3]]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'l2u' && isset($parts[3])) {
            $this->state->set($user, 'admin_inbound_l2tp_update', ['id' => (int) $parts[3], 'step' => 'label']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_l2tp_label', $user, 'Send new label'));

            return;
        }
        if ($action === 'pt' && isset($parts[3])) {
            $result = $this->mutate->applyForUser($user, 'panel_test', ['panel_id' => (int) $parts[3]]);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }
        if ($action === 'sy' && isset($parts[3])) {
            $panelId = (int) $parts[3];
            if (! $this->mayAccessPanel($user, $panelId)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                return;
            }
            $result = $this->configsSync->syncPanelToDb($panelId, true);
            $msg = ! empty($result['ok'])
                ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Synced')
                : '⛔ '.(string) ($result['message'] ?? $result['code'] ?? 'failed');
            $this->send($ctx, $chatId, $msg);

            return;
        }
        if ($action === 'pd' && isset($parts[3])) {
            $panelId = (int) $parts[3];
            if (($parts[4] ?? '') === 'y') {
                if (! $this->mayAccessPanel($user, $panelId)) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                    return;
                }
                $result = $this->mutate->applyForUser($user, 'panel_xp', ['id' => $panelId, 'active' => 0]);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

                return;
            }
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.confirm_panel_delete', $user, 'Deactivate panel #'.$panelId.'?'), [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '✅', 'callback_data' => 'pnl:inb:pd:'.$panelId.':y'],
                        ['text' => '❌', 'callback_data' => 'noop'],
                    ]],
                ],
            ]);

            return;
        }
        if ($action === 'mp' && isset($parts[3])) {
            $panelId = (int) $parts[3];
            if (! $this->mayAccessPanel($user, $panelId)) {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                return;
            }
            $map = $this->inboundMap->getMap($panelId);
            $body = "Inbound map panel #{$panelId}\n".mb_substr(json_encode($map, JSON_UNESCAPED_UNICODE), 0, 3500);
            $body .= "\n\nSend map JSON to save";
            $this->state->set($user, 'admin_inbound_map_edit', ['panel_id' => $panelId, 'step' => 'json']);
            $this->send($ctx, $chatId, $body);

            return;
        }
        if ($action === 'rb' && isset($parts[3])) {
            $panelId = (int) $parts[3];
            if (($parts[4] ?? '') === 'y') {
                if (! $this->mayAccessPanel($user, $panelId)) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                    return;
                }
                $result = $this->panelMaintenance->rebuildFromDb(['panel_id' => $panelId]);
                $msg = ! empty($result['ok'])
                    ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Done')
                    : '⛔ '.(string) ($result['message'] ?? 'failed');
                $this->send($ctx, $chatId, $msg);

                return;
            }
            $this->send($ctx, $chatId, 'Rebuild panel #'.$panelId.' from DB?', [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '✅', 'callback_data' => 'pnl:inb:rb:'.$panelId.':y'],
                        ['text' => '❌', 'callback_data' => 'noop'],
                    ]],
                ],
            ]);

            return;
        }
        if ($action === 'f5' && isset($parts[3])) {
            $panelId = (int) $parts[3];
            if (($parts[4] ?? '') === 'y') {
                if (! $this->mayAccessPanel($user, $panelId)) {
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                    return;
                }
                $result = $this->panelMaintenance->fix51200Traffic(['panel_id' => $panelId]);
                $msg = ! empty($result['ok'])
                    ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Done')
                    : '⛔ '.(string) ($result['message'] ?? 'failed');
                $this->send($ctx, $chatId, $msg);

                return;
            }
            $this->send($ctx, $chatId, 'Fix 51200 traffic on panel #'.$panelId.'?', [
                'reply_markup' => [
                    'inline_keyboard' => [[
                        ['text' => '✅', 'callback_data' => 'pnl:inb:f5:'.$panelId.':y'],
                        ['text' => '❌', 'callback_data' => 'noop'],
                    ]],
                ],
            ]);

            return;
        }
        if ($action === 'cl' && isset($parts[3], $parts[4], $parts[5])) {
            $sub = (string) $parts[3];
            $panelId = (int) $parts[4];
            $inboundId = (int) $parts[5];
            $email = (string) ($parts[6] ?? '');
            $op = match ($sub) {
                'tg' => 'configs_client_toggle_enable',
                'rs' => 'configs_client_reset_traffic',
                'dl' => 'configs_client_delete',
                default => '',
            };
            if ($op === '') {
                return;
            }
            $params = ['panel_id' => $panelId, 'inbound_id' => $inboundId, 'email' => $email];
            if ($sub === 'tg') {
                $params['enabled'] = empty($parts[7]) || (string) $parts[7] !== '0';
            }
            $result = $this->mutate->applyForUser($user, $op, $params);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    /** @param  array<int, string>  $parts */
    public function handleIlCallback(BotContext $ctx, array $parts, SvpUser $user, int $chatId): void
    {
        $idx = (int) ($parts[2] ?? 0);
        $data = $this->state->data($user);
        $clients = is_array($data['inb_clients'] ?? null) ? $data['inb_clients'] : [];
        $client = $clients[$idx] ?? null;
        if (! is_array($client)) {
            $this->send($ctx, $chatId, 'Client not found');

            return;
        }
        $this->state->set($user, 'admin_inbound_link', [
            'panel_id' => (int) ($client['panel_id'] ?? 0),
            'inbound_id' => (int) ($client['inbound_id'] ?? 0),
            'email' => (string) ($client['email'] ?? ''),
        ]);
        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_inbound_service_id', $user, 'Send service_id to link'));
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $addBtn = $this->texts->getForUser('btn.admin.l2tp_add', $user, 'Add L2TP');
        if (trim($text) === $addBtn) {
            $this->state->set($user, 'admin_inbound_l2tp_add', ['step' => 'host']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_l2tp_host', $user, 'Send host'));

            return true;
        }

        $buttons = [
            $this->texts->getForUser('btn.admin.panel_add', $user, 'Add panel') => ['state' => 'admin_inbound_panel_xp', 'step' => 'name', 'prompt' => 'Send panel name'],
            $this->texts->getForUser('btn.admin.panel_delete', $user, 'Delete panel') => ['state' => 'admin_inbound_panel_delete', 'step' => 'panel_id', 'prompt' => 'Send panel_id to deactivate'],
            $this->texts->getForUser('btn.admin.configs_sync', $user, 'Sync configs') => ['state' => 'admin_inbound_configs_sync', 'step' => 'panel_id', 'prompt' => 'Send panel_id to sync'],
            $this->texts->getForUser('btn.admin.configs_batch', $user, 'Batch clients') => ['state' => 'admin_inbound_configs_batch', 'step' => 'panel_id', 'prompt' => 'Send panel_id'],
            $this->texts->getForUser('btn.admin.configs_patch', $user, 'Patch client') => ['state' => 'admin_inbound_configs_patch', 'step' => 'client_id', 'prompt' => 'Send client_id'],
            $this->texts->getForUser('btn.admin.inbound_map_edit', $user, 'Edit inbound map') => ['state' => 'admin_inbound_map_edit', 'step' => 'panel_id', 'prompt' => 'Send panel_id'],
            $this->texts->getForUser('btn.admin.configs_delete_expired', $user, 'Delete expired configs') => ['state' => 'admin_inbound_configs_delete_expired', 'step' => 'panel_id', 'prompt' => 'Send panel_id'],
            $this->texts->getForUser('btn.admin.configs_assign_plan', $user, 'Assign plan') => ['state' => 'admin_inbound_configs_assign_plan', 'step' => 'panel_id', 'prompt' => 'Send panel_id'],
        ];
        foreach ($buttons as $btn => $cfg) {
            if (trim($text) === $btn) {
                $this->state->set($user, (string) $cfg['state'], ['step' => (string) $cfg['step']]);
                $this->send($ctx, $chatId, (string) $cfg['prompt']);

                return true;
            }
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $trimmed = trim($text);
        $data = $this->state->data($user);

        if ($state === 'admin_inbound_link' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'inbound_link', [
                'service_id' => (int) $trimmed,
                'panel_id' => (int) ($data['panel_id'] ?? 0),
                'inbound_id' => (int) ($data['inbound_id'] ?? 0),
                'email' => (string) ($data['email'] ?? ''),
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state !== 'admin_inbound_l2tp_add') {
            if ($state === 'admin_inbound_l2tp_update') {
                $this->routeL2tpUpdate($ctx, $user, $chatId, $trimmed, $data);

                return;
            }
            if ($state === 'admin_inbound_panel_xp') {
                $this->routePanelXp($ctx, $user, $chatId, $trimmed, $data);

                return;
            }
            if ($state === 'admin_inbound_configs_delete_expired') {
                if (is_numeric($trimmed)) {
                    $result = $this->mutate->applyForUser($user, 'configs_delete_expired_linked', [
                        'panel_id' => (int) $trimmed,
                    ]);
                    $this->state->clear($user);
                    $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
                }

                return;
            }
            if ($state === 'admin_inbound_configs_assign_plan') {
                $this->routeConfigsAssignPlan($ctx, $user, $chatId, $trimmed, $data);

                return;
            }
            if ($state === 'admin_inbound_configs_sync' && is_numeric($trimmed)) {
                $panelId = (int) $trimmed;
                if (! $this->mayAccessPanel($user, $panelId)) {
                    $this->state->clear($user);
                    $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                    return;
                }
                $result = $this->configsSync->syncPanelToDb($panelId, true);
                $this->state->clear($user);
                $msg = ! empty($result['ok'])
                    ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Synced')
                    : '⛔ '.(string) ($result['message'] ?? 'failed');
                $this->send($ctx, $chatId, $msg);

                return;
            }
            if ($state === 'admin_inbound_panel_delete') {
                $this->routePanelDelete($ctx, $user, $chatId, $trimmed, $data);

                return;
            }
            if ($state === 'admin_inbound_configs_batch') {
                $this->routeConfigsBatch($ctx, $user, $chatId, $trimmed, $data);

                return;
            }
            if ($state === 'admin_inbound_configs_patch') {
                $this->routeConfigsPatch($ctx, $user, $chatId, $trimmed, $data);

                return;
            }
            if ($state === 'admin_inbound_map_edit') {
                $this->routeInboundMapEdit($ctx, $user, $chatId, $trimmed, $data);

                return;
            }

            return;
        }

        $step = (string) ($data['step'] ?? '');
        if ($step === 'host' && $trimmed !== '') {
            $this->state->set($user, 'admin_inbound_l2tp_add', ['step' => 'label', 'host' => $trimmed]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_l2tp_label', $user, 'Send label'));

            return;
        }
        if ($step === 'label' && $trimmed !== '') {
            $result = $this->mutate->applyForUser($user, 'l2tp_add', [
                'l2tp_host' => (string) ($data['host'] ?? ''),
                'label' => $trimmed,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function sendPanelList(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $rows = DB::table('svp_panels')->orderBy('sort_order')->limit(10)->get();
        $body = "XUI panels\n";
        $body .= "\n".$this->texts->getForUser('btn.admin.panel_add', $user, 'Add panel');
        $ik = [];
        foreach ($rows as $r) {
            $pid = (int) $r->id;
            $body .= '• #'.$pid.' '.(string) ($r->label ?? '')."\n";
            $ik[] = [['text' => 'Inbounds #'.$pid, 'callback_data' => 'pnl:inb:pn:'.$pid]];
            $ik[] = [
                ['text' => 'Test #'.$pid, 'callback_data' => 'pnl:inb:pt:'.$pid],
                ['text' => 'Sync #'.$pid, 'callback_data' => 'pnl:inb:sy:'.$pid],
            ];
            $ik[] = [
                ['text' => 'Map #'.$pid, 'callback_data' => 'pnl:inb:mp:'.$pid],
                ['text' => 'Del #'.$pid, 'callback_data' => 'pnl:inb:pd:'.$pid],
            ];
            $ik[] = [
                ['text' => 'Rebuild #'.$pid, 'callback_data' => 'pnl:inb:rb:'.$pid],
                ['text' => 'Fix51200 #'.$pid, 'callback_data' => 'pnl:inb:f5:'.$pid],
            ];
        }
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function sendConfigMap(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $panels = DB::table('svp_panels')->orderBy('sort_order')->limit(8)->get();
        $catalog = (int) DB::table('svp_inbound_display_catalog')->count();
        $body = "Inbound configs\nCatalog entries: {$catalog}\n\nPick panel:";
        $body .= "\n".$this->texts->getForUser('btn.admin.configs_delete_expired', $user, 'Delete expired configs');
        $body .= "\n".$this->texts->getForUser('btn.admin.configs_assign_plan', $user, 'Assign plan');
        $body .= "\n".$this->texts->getForUser('btn.admin.configs_sync', $user, 'Sync configs');
        $body .= "\n".$this->texts->getForUser('btn.admin.configs_batch', $user, 'Batch clients');
        $body .= "\n".$this->texts->getForUser('btn.admin.configs_patch', $user, 'Patch client');
        $ik = [];
        foreach ($panels as $p) {
            $pid = (int) $p->id;
            $map = $this->inboundMap->getMap($pid);
            $body .= "\n#{$pid} map entries: ".count($map);
            $ik[] = [['text' => 'Panel #'.$pid, 'callback_data' => 'pnl:inb:pn:'.$pid]];
        }
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function sendInboundsForPanel(BotContext $ctx, int $chatId, SvpUser $user, int $panelId): void
    {
        if (! $this->mayAccessPanel($user, $panelId)) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $inboundIds = [];
        if (Schema::hasTable('svp_panel_inbound_api')) {
            $inboundIds = DB::table('svp_panel_inbound_api')
                ->where('panel_id', $panelId)
                ->pluck('inbound_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }
        if ($inboundIds === [] && Schema::hasTable('svp_panel_inbound_clients')) {
            $inboundIds = DB::table('svp_panel_inbound_clients')
                ->where('panel_id', $panelId)
                ->distinct()
                ->pluck('inbound_id')
                ->map(fn ($v) => (int) $v)
                ->all();
        }
        $body = "Panel #{$panelId} inbounds\n";
        $ik = [];
        if ($inboundIds === []) {
            $body .= '—';
        } else {
            foreach (array_slice($inboundIds, 0, 20) as $iid) {
                $body .= "• inbound {$iid}\n";
                $ik[] = [
                    ['text' => 'Clients '.$iid, 'callback_data' => 'pnl:inb:ib:'.$panelId.':'.$iid],
                    ['text' => 'Autolink', 'callback_data' => 'pnl:inb:al:'.$panelId.':'.$iid],
                ];
            }
        }
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function sendInboundClients(BotContext $ctx, int $chatId, SvpUser $user, int $panelId, int $inboundId): void
    {
        if (! Schema::hasTable('svp_panel_inbound_clients')) {
            $this->send($ctx, $chatId, 'No clients cache');

            return;
        }
        $rows = DB::table('svp_panel_inbound_clients')
            ->where('panel_id', $panelId)
            ->where('inbound_id', $inboundId)
            ->orderBy('email')
            ->limit(self::PAGE_SIZE)
            ->get();
        $body = "Panel {$panelId} / inbound {$inboundId}\n";
        $ik = [];
        $clientList = [];
        foreach ($rows as $r) {
            $email = (string) ($r->email ?? '');
            $body .= '• '.$email."\n";
            if ($email !== '') {
                $clientList[] = ['panel_id' => $panelId, 'inbound_id' => $inboundId, 'email' => $email];
                $ik[] = [
                    ['text' => 'Link', 'callback_data' => 'pnl:inb:lk:'.$panelId.':'.$inboundId.':'.(int) $r->id],
                    ['text' => 'Toggle', 'callback_data' => 'pnl:inb:cl:tg:'.$panelId.':'.$inboundId.':'.$email],
                    ['text' => 'Reset', 'callback_data' => 'pnl:inb:cl:rs:'.$panelId.':'.$inboundId.':'.$email],
                    ['text' => 'Del', 'callback_data' => 'pnl:inb:cl:dl:'.$panelId.':'.$inboundId.':'.$email],
                ];
            }
        }
        $this->state->set($user, 'admin_inbound_clients', ['inb_clients' => $clientList]);
        if ($rows->isEmpty()) {
            $body .= '—';
        }
        $ik[] = [['text' => 'Autolink', 'callback_data' => 'pnl:inb:al:'.$panelId.':'.$inboundId]];
        $this->send($ctx, $chatId, $body, ['reply_markup' => ['inline_keyboard' => $ik]]);
    }

    protected function sendL2tpList(BotContext $ctx, int $chatId, SvpUser $user, int $offset): void
    {
        $off = max(0, $offset);
        $total = (int) DB::table('svp_l2tp_servers')->count();
        $rows = DB::table('svp_l2tp_servers')->orderBy('id')->offset($off)->limit(self::PAGE_SIZE)->get();
        $body = "L2TP servers ({$total})\n";
        $ik = [];
        foreach ($rows as $r) {
            $id = (int) $r->id;
            $body .= "#{$id} ".(string) ($r->label ?? '').' '.(string) ($r->l2tp_host ?? '')."\n";
            $ik[] = [['text' => '🗑 #'.$id, 'callback_data' => 'pnl:inb:l2d:'.$id]];
            $ik[] = [['text' => '✏️ #'.$id, 'callback_data' => 'pnl:inb:l2u:'.$id]];
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:inb:l2p:'.max(0, $off - self::PAGE_SIZE)];
        }
        if ($total > $off + self::PAGE_SIZE) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:inb:l2p:'.($off + self::PAGE_SIZE)];
        }
        if ($nav !== []) {
            $ik[] = $nav;
        }
        $body .= "\n".$this->texts->getForUser('btn.admin.l2tp_add', $user, 'Add L2TP');
        $this->send($ctx, $chatId, $body, $ik !== [] ? ['reply_markup' => ['inline_keyboard' => $ik]] : []);
    }

    protected function resolveServiceIdForClient(int $clientId): int
    {
        if ($clientId < 1 || ! Schema::hasTable('svp_panel_inbound_clients')) {
            return 0;
        }
        $client = DB::table('svp_panel_inbound_clients')->where('id', $clientId)->first();
        if (! $client) {
            return 0;
        }

        return (int) (DB::table('svp_services')
            ->where('panel_id', (int) $client->panel_id)
            ->where('inbound_id', (int) $client->inbound_id)
            ->where('email', (string) $client->email)
            ->whereNull('deleted_at')
            ->value('id') ?? 0);
    }

    protected function mayAccessPanel(SvpUser $user, int $panelId): bool
    {
        if ($user->role !== 'reseller') {
            return true;
        }

        return in_array($panelId, $this->resellerScope->allowedPanelIdsFor((int) $user->id), true);
    }

    /** @param  array<string, mixed>  $data */
    protected function routePanelXp(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'name');
        if ($step === 'panel_id' && is_numeric($trimmed)) {
            $this->state->set($user, 'admin_inbound_panel_xp', ['step' => 'name', 'panel_id' => (int) $trimmed]);
            $this->send($ctx, $chatId, 'Send panel name (new) or label (update)');

            return;
        }
        if ($step === 'name' && $trimmed !== '') {
            $this->state->set($user, 'admin_inbound_panel_xp', [
                'step' => 'url',
                'name' => $trimmed,
                'panel_id' => (int) ($data['panel_id'] ?? 0),
            ]);
            $this->send($ctx, $chatId, 'Send panel URL');

            return;
        }
        if ($step === 'url' && $trimmed !== '') {
            $this->state->set($user, 'admin_inbound_panel_xp', [
                'step' => 'user',
                'name' => (string) ($data['name'] ?? ''),
                'url' => $trimmed,
                'panel_id' => (int) ($data['panel_id'] ?? 0),
            ]);
            $this->send($ctx, $chatId, 'Send panel username');

            return;
        }
        if ($step === 'user' && $trimmed !== '') {
            $params = [
                'name' => (string) ($data['name'] ?? ''),
                'url' => (string) ($data['url'] ?? ''),
                'username' => $trimmed,
            ];
            $pid = (int) ($data['panel_id'] ?? 0);
            if ($pid > 0) {
                $params['panel_id'] = $pid;
            }
            $result = $this->mutate->applyForUser($user, 'panel_xp', $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routeConfigsAssignPlan(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'panel_id');
        if ($step === 'panel_id' && is_numeric($trimmed)) {
            $this->state->set($user, 'admin_inbound_configs_assign_plan', [
                'step' => 'inbound',
                'panel_id' => (int) $trimmed,
            ]);
            $this->send($ctx, $chatId, 'Send inbound_id|email|plan_id');

            return;
        }
        if ($step === 'inbound' && str_contains($trimmed, '|')) {
            $parts = array_map('trim', explode('|', $trimmed));
            $result = $this->mutate->applyForUser($user, 'configs_assign_plan', [
                'panel_id' => (int) ($data['panel_id'] ?? 0),
                'inbound_id' => (int) ($parts[0] ?? 0),
                'email' => (string) ($parts[1] ?? ''),
                'plan_id' => (int) ($parts[2] ?? 0),
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routePanelDelete(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'panel_id');
        if ($step === 'panel_id' && is_numeric($trimmed)) {
            $panelId = (int) $trimmed;
            if (! $this->mayAccessPanel($user, $panelId)) {
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                return;
            }
            $this->state->set($user, 'admin_inbound_panel_delete', ['step' => 'confirm', 'panel_id' => $panelId]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_panel_delete_confirm', $user, 'Reply yes to deactivate panel #'.$panelId));

            return;
        }
        if ($step === 'confirm' && in_array(mb_strtolower($trimmed), ['yes', 'y', 'بله', 'تایید'], true)) {
            $result = $this->mutate->applyForUser($user, 'panel_xp', [
                'id' => (int) ($data['panel_id'] ?? 0),
                'active' => 0,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routeConfigsBatch(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'panel_id');
        if ($step === 'panel_id' && is_numeric($trimmed)) {
            $this->state->set($user, 'admin_inbound_configs_batch', [
                'step' => 'inbound',
                'panel_id' => (int) $trimmed,
            ]);
            $this->send($ctx, $chatId, 'Send inbound_id|action|email1,email2');

            return;
        }
        if ($step === 'inbound' && str_contains($trimmed, '|')) {
            $parts = array_map('trim', explode('|', $trimmed, 3));
            $emails = array_values(array_filter(array_map('trim', explode(',', (string) ($parts[2] ?? '')))));
            $result = $this->mutate->applyForUser($user, 'configs_clients_batch', [
                'panel_id' => (int) ($data['panel_id'] ?? 0),
                'inbound_id' => (int) ($parts[0] ?? 0),
                'action' => (string) ($parts[1] ?? 'noop'),
                'emails' => $emails,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routeConfigsPatch(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'client_id');
        if ($step === 'client_id' && is_numeric($trimmed)) {
            $this->state->set($user, 'admin_inbound_configs_patch', [
                'step' => 'fields',
                'client_id' => (int) $trimmed,
            ]);
            $this->send($ctx, $chatId, 'Send patch JSON e.g. {"note":"text","limit_ip":2}');

            return;
        }
        if ($step === 'fields' && $trimmed !== '') {
            $patch = json_decode($trimmed, true);
            if (! is_array($patch)) {
                return;
            }
            $clientId = (int) ($data['client_id'] ?? 0);
            $params = $patch;
            if ($clientId > 0 && ! isset($params['service_id'])) {
                $serviceId = $this->resolveServiceIdForClient($clientId);
                if ($serviceId > 0) {
                    $params['service_id'] = $serviceId;
                } else {
                    $params['client_id'] = $clientId;
                }
            }
            $result = $this->mutate->applyForUser($user, 'configs_panel_client_patch', $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routeInboundMapEdit(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'panel_id');
        $panelId = (int) ($data['panel_id'] ?? 0);
        if ($step === 'panel_id' && is_numeric($trimmed)) {
            $panelId = (int) $trimmed;
            if (! $this->mayAccessPanel($user, $panelId)) {
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

                return;
            }
            $map = $this->inboundMap->getMap($panelId);
            $this->state->set($user, 'admin_inbound_map_edit', ['step' => 'json', 'panel_id' => $panelId]);
            $this->send($ctx, $chatId, "Current map:\n".mb_substr(json_encode($map, JSON_UNESCAPED_UNICODE), 0, 2000)."\n\nSend new map JSON");

            return;
        }
        if ($step === 'json' && $trimmed !== '' && $panelId > 0) {
            $map = json_decode($trimmed, true);
            if (! is_array($map)) {
                return;
            }
            $this->inboundMap->saveMap($panelId, $map);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Map saved'));
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routeL2tpUpdate(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data): void
    {
        $step = (string) ($data['step'] ?? 'label');
        if ($step === 'label' && $trimmed !== '') {
            $this->state->set($user, 'admin_inbound_l2tp_update', [
                'step' => 'host',
                'id' => (int) ($data['id'] ?? 0),
                'label' => $trimmed,
            ]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_l2tp_host', $user, 'Send host (or - to skip)'));

            return;
        }
        if ($step === 'host') {
            $params = [
                'id' => (int) ($data['id'] ?? 0),
                'label' => (string) ($data['label'] ?? ''),
            ];
            if ($trimmed !== '' && $trimmed !== '-') {
                $params['l2tp_host'] = $trimmed;
            }
            $result = $this->mutate->applyForUser($user, 'l2tp_update', $params);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $panels = (int) DB::table('svp_panels')->count();

        return "Inbounds\nPanels: {$panels}";
    }
}
