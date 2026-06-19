<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminEconomicsHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected BotAdminMutateService $mutate,
        protected BotStateService $state,
        protected AdminPermissionService $permissions,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.economics', $user, '📉 Economics');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }

        match ($tabKey) {
            'panel_economics', 'unit_economics' => $this->sendPanelEconomics($ctx, $chatId, $user),
            'unit_economics_config' => $this->sendUnitConfig($ctx, $chatId, $user),
            default => $this->send($ctx, $chatId, $this->sectionIntro($user)),
        };
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        if ($this->permissions->permissionActorId($user) > 0) {
            return false;
        }

        $trimmed = trim($text);
        $buttons = [
            $this->texts->getForUser('btn.admin.economics_mark_paid', $user, 'Mark paid') => 'admin_economics_mark_paid:panel',
            $this->texts->getForUser('btn.admin.economics_save_config', $user, 'Save config') => 'admin_economics_config:usd',
            $this->texts->getForUser('btn.admin.economics_add_line', $user, 'Add line') => 'admin_economics_line_add:panel',
            $this->texts->getForUser('btn.admin.economics_edit_line', $user, 'Edit line') => 'admin_economics_edit_line:line_id',
            $this->texts->getForUser('btn.admin.economics_delete_line', $user, 'Delete line') => 'admin_economics_delete_line:line_id',
            $this->texts->getForUser('btn.admin.economics_deactivate_line', $user, 'Deactivate line') => 'admin_economics_deactivate_line:line_id',
            $this->texts->getForUser('btn.admin.economics_shared_lines', $user, 'Shared cost') => 'admin_economics_shared:cost',
            $this->texts->getForUser('btn.admin.economics_unit_save', $user, 'Save unit cost') => 'admin_economics_unit_save:panel',
        ];

        foreach ($buttons as $label => $stateSpec) {
            if ($trimmed !== $label) {
                continue;
            }
            [$state, $step] = explode(':', $stateSpec, 2);
            $this->state->set($user, $state, ['step' => $step]);
            $this->send($ctx, $chatId, $this->promptForStep($state, $step, $user));

            return true;
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $trimmed = trim($text);
        $data = $this->state->data($user);

        if ($state === 'admin_economics_mark_paid' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'panel_economics_mark_paid', ['panel_id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_economics_config') {
            $step = (string) ($data['step'] ?? 'usd');
            if ($step === 'usd' && is_numeric(str_replace(',', '.', $trimmed))) {
                $this->state->set($user, 'admin_economics_config', [
                    'step' => 'volume_mode',
                    'usd_rate' => (float) str_replace(',', '.', $trimmed),
                ]);
                $this->send($ctx, $chatId, 'Send volume_mode (rolling/fixed)');

                return;
            }
            if ($step === 'volume_mode') {
                $this->state->set($user, 'admin_economics_config', array_merge($data, [
                    'step' => 'volume_days',
                    'volume_mode' => $trimmed,
                ]));
                $this->send($ctx, $chatId, 'Send volume_window_days');

                return;
            }
            if ($step === 'volume_days' && is_numeric($trimmed)) {
                $result = $this->mutate->applyForUser($user, 'unit_economics_config_save', [
                    'usd_rate' => (float) ($data['usd_rate'] ?? 0),
                    'volume_mode' => (string) ($data['volume_mode'] ?? 'rolling'),
                    'volume_window_days' => (int) $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_economics_shared' && is_numeric(str_replace(',', '.', $trimmed))) {
            $result = $this->mutate->applyForUser($user, 'shared_economics_save', [
                'shared_cost_per_gb' => (float) str_replace(',', '.', $trimmed),
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if (in_array($state, ['admin_economics_line_add', 'admin_economics_edit_line'], true)) {
            $this->routeLineWizard($ctx, $user, $chatId, $trimmed, $data, $state === 'admin_economics_edit_line' ? 'edit' : 'add');

            return;
        }

        if (in_array($state, ['admin_economics_delete_line', 'admin_economics_deactivate_line'], true)) {
            if (! is_numeric($trimmed)) {
                return;
            }
            $lineId = (int) $trimmed;
            $panelId = (int) DB::table('svp_panel_economics_lines')->where('id', $lineId)->value('panel_id');
            $action = $state === 'admin_economics_delete_line' ? 'delete' : 'deactivate';
            $result = $this->mutate->applyForUser($user, 'panel_economics_save', [
                'panel_id' => $panelId,
                'line_action' => $action,
                'line_id' => $lineId,
            ]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_economics_unit_save') {
            $step = (string) ($data['step'] ?? 'panel');
            if ($step === 'panel' && is_numeric($trimmed)) {
                $this->state->set($user, 'admin_economics_unit_save', ['step' => 'cost', 'panel_id' => (int) $trimmed]);
                $this->send($ctx, $chatId, 'Send server_cost_monthly');

                return;
            }
            if ($step === 'cost' && is_numeric(str_replace(',', '.', $trimmed))) {
                $result = $this->mutate->applyForUser($user, 'unit_economics_save', [
                    'panel_id' => (int) ($data['panel_id'] ?? 0),
                    'server_cost_monthly' => (float) str_replace(',', '.', $trimmed),
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }
        }
    }

    /** @param  array<string, mixed>  $data */
    protected function routeLineWizard(BotContext $ctx, SvpUser $user, int $chatId, string $trimmed, array $data, string $action): void
    {
        $step = (string) ($data['step'] ?? 'panel');
        if ($step === 'panel' && is_numeric($trimmed)) {
            $next = $action === 'edit' ? 'line_id' : 'line';
            $this->state->set($user, $action === 'edit' ? 'admin_economics_edit_line' : 'admin_economics_line_add', [
                'step' => $next,
                'panel_id' => (int) $trimmed,
            ]);
            $this->send($ctx, $chatId, $action === 'edit' ? 'Send line_id' : 'Send line: label|amount|active(0/1)');

            return;
        }
        if ($step === 'line_id' && is_numeric($trimmed)) {
            $this->state->set($user, 'admin_economics_edit_line', [
                'step' => 'line',
                'panel_id' => (int) ($data['panel_id'] ?? 0),
                'line_id' => (int) $trimmed,
            ]);
            $this->send($ctx, $chatId, 'Send line: label|amount|active(0/1)');

            return;
        }
        if ($step === 'line' && str_contains($trimmed, '|')) {
            $parts = array_map('trim', explode('|', $trimmed));
            $payload = [
                'panel_id' => (int) ($data['panel_id'] ?? 0),
                'line_action' => $action,
                'label' => (string) ($parts[0] ?? ''),
                'amount' => (float) str_replace(',', '.', (string) ($parts[1] ?? '0')),
                'active' => (int) ($parts[2] ?? 1),
            ];
            if ($action === 'edit') {
                $payload['line_id'] = (int) ($data['line_id'] ?? 0);
            }
            $result = $this->mutate->applyForUser($user, 'panel_economics_save', $payload);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function promptForStep(string $state, string $step, SvpUser $user): string
    {
        return match ($step) {
            'panel' => $this->texts->getForUser('msg.admin.prompt_economics_panel_id', $user, 'Send panel_id'),
            'line_id' => 'Send line_id',
            'usd' => $this->texts->getForUser('msg.admin.prompt_economics_usd_rate', $user, 'Send USD rate'),
            'cost' => 'Send shared_cost_per_gb',
            default => 'Send value',
        };
    }

    protected function sendPanelEconomics(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $panels = DB::table('svp_panels')->orderBy('sort_order')->limit(8)->get();
        $body = "Panel economics\n";
        foreach ($panels as $p) {
            $pid = (int) $p->id;
            $lines = Schema::hasTable('svp_panel_economics_lines')
                ? (int) DB::table('svp_panel_economics_lines')->where('panel_id', $pid)->count()
                : 0;
            $body .= "#{$pid} ".(string) ($p->label ?? '')." lines={$lines}\n";
        }
        foreach ([
            'btn.admin.economics_mark_paid',
            'btn.admin.economics_add_line',
            'btn.admin.economics_edit_line',
            'btn.admin.economics_delete_line',
            'btn.admin.economics_deactivate_line',
            'btn.admin.economics_shared_lines',
            'btn.admin.economics_unit_save',
        ] as $key) {
            $body .= "\n".$this->texts->getForUser($key, $user, $key);
        }
        $this->send($ctx, $chatId, $body);
    }

    protected function sendUnitConfig(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $cfg = Schema::hasTable('svp_unit_economics_config')
            ? DB::table('svp_unit_economics_config')->first()
            : null;
        $body = "Unit economics config\nUSD: ".($cfg?->usd_rate ?? '—');
        $body .= "\nMode: ".($cfg?->volume_mode ?? '—');
        $body .= "\nWindow days: ".($cfg?->volume_window_days ?? '—');
        $body .= "\n\n".$this->texts->getForUser('btn.admin.economics_save_config', $user, 'Save config');
        $this->send($ctx, $chatId, $body);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        $revenue = (float) DB::table('svp_transactions')->where('status', 'completed')->sum('amount');
        $panels = (int) DB::table('svp_panels')->count();

        return "Economics\nCompleted tx: ".number_format($revenue, 0)."\nPanels: {$panels}";
    }
}
