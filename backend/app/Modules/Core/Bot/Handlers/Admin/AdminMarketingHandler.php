<?php

namespace App\Modules\Core\Bot\Handlers\Admin;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;
use App\Modules\Core\Bot\Services\AdminKeyboardBuilder;
use App\Modules\Core\Bot\Services\AdminPermissionService;
use App\Modules\Core\Bot\Services\BotAdminMutateService;
use App\Modules\Core\Bot\Services\BotRuntime;
use App\Modules\Core\Bot\Services\BotStateService;
use App\Modules\Core\Bot\Services\TextService;
use App\Modules\Core\Services\Portal\PortalAdminService;
use App\Services\SettingsStore;
use Illuminate\Support\Facades\DB;

class AdminMarketingHandler extends AbstractAdminHandler
{
    public function __construct(
        BotRuntime $runtime,
        TextService $texts,
        protected AdminPermissionService $permissions,
        protected SettingsStore $settings,
        protected BotStateService $state,
        protected BotAdminMutateService $mutate,
        protected AdminKeyboardBuilder $keyboards,
        protected PortalAdminService $portalAdmin,
    ) {
        parent::__construct($runtime, $texts);
    }

    use AdminHandlerTrait;

    protected function navLabel(SvpUser $user): string
    {
        return $this->texts->getForUser('btn.admin.marketing', $user, '📣 Marketing');
    }

    public function openTab(BotContext $ctx, int $chatId, SvpUser $user, string $tabKey): void
    {
        match ($tabKey) {
            'referral' => $this->openReferral($ctx, $chatId, $user),
            'marketing_lifecycle' => $this->openLifecycle($ctx, $chatId, $user),
            'discounts' => $this->openDiscounts($ctx, $chatId, $user),
            default => $this->send($ctx, $chatId, $this->sectionIntro($user)),
        };
    }

    public function openBroadcastPrompt(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        if (! $this->permissions->mayCallOp($user, 'broadcast')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $this->state->set($user, 'admin_broadcast', ['step' => 'text']);
        $body = $this->texts->getForUser('msg.admin.prompt_broadcast', $user, 'Send broadcast text');
        $body .= "\n\n".$this->texts->getForUser('btn.admin.broadcast_cancel', $user, 'Cancel broadcast');
        $body .= "\n".$this->texts->getForUser('btn.admin.broadcast_run_worker', $user, 'Run broadcast worker');
        $this->send($ctx, $chatId, $body);
    }

    public function handleBroadcastText(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        if (! $this->permissions->mayCallOp($user, 'broadcast')) {
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $data = $this->state->data($user);
        $step = (string) ($data['step'] ?? 'text');
        $trimmed = trim($text);

        if ($step === 'text') {
            if ($trimmed === '') {
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_broadcast', $user, 'Send broadcast text'));

                return;
            }
            $this->state->set($user, 'admin_broadcast', ['step' => 'segment', 'bc_text' => $trimmed]);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_broadcast_segment', $user, 'Pick target'), [
                'reply_markup' => $this->keyboards->inlineBroadcastSegments(),
            ]);

            return;
        }

        if ($step === 'confirm') {
            if (in_array(mb_strtolower($trimmed), ['yes', 'y', 'بله', 'تایید'], true)) {
                $result = $this->mutate->applyForUser($user, 'broadcast_send', [
                    'bc_text' => (string) ($data['bc_text'] ?? ''),
                    'bc_targets' => (string) ($data['bc_targets'] ?? 'telegram'),
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

                return;
            }
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.buy.cancelled', $user, 'Cancelled'));
        }
    }

    /** @param  array<int, string>  $parts */
    public function handleBroadcastCallback(BotContext $ctx, SvpUser $user, int $chatId, array $parts): void
    {
        if (! $this->permissions->mayCallOp($user, 'broadcast')) {
            return;
        }
        $seg = (string) ($parts[2] ?? '');
        $targets = match ($seg) {
            'bl' => 'bale',
            'both' => 'both',
            default => 'telegram',
        };
        $data = $this->state->data($user);
        $this->state->set($user, 'admin_broadcast', [
            'step' => 'confirm',
            'bc_text' => (string) ($data['bc_text'] ?? ''),
            'bc_targets' => $targets,
        ]);
        $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_broadcast_confirm', $user, "Send to {$targets}? Reply yes to confirm"));
    }

    public function routeText(BotContext $ctx, SvpUser $user, int $chatId, string $text): bool
    {
        $trimmed = trim($text);
        $newDiscount = $this->texts->getForUser('btn.admin.discount_new', $user, 'New code');
        $delDiscount = $this->texts->getForUser('btn.admin.discount_delete', $user, 'Delete');
        $newLifecycle = $this->texts->getForUser('btn.admin.lifecycle_new', $user, 'New rule');
        $runLifecycle = $this->texts->getForUser('btn.admin.lifecycle_run', $user, 'Run now');
        $refToggle = $this->texts->getForUser('btn.admin.referral_toggle', $user, 'Toggle');
        $refPercent = $this->texts->getForUser('btn.admin.referral_percent', $user, 'Percent');

        if ($trimmed === $newDiscount) {
            $this->state->set($user, 'admin_discount_code', ['step' => 'code']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_code', $user, 'Send discount code'));

            return true;
        }
        if ($trimmed === $delDiscount) {
            $this->state->set($user, 'admin_discount_delete', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_delete', $user, 'Send discount id'));

            return true;
        }
        if ($trimmed === $newLifecycle) {
            $this->state->set($user, 'admin_lifecycle_new', ['step' => 'segment']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_segment', $user, 'Send segment key'));

            return true;
        }
        if ($trimmed === $runLifecycle) {
            $this->state->set($user, 'admin_lifecycle_run', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_run', $user, 'Send rule id'));

            return true;
        }
        if ($trimmed === $refToggle && $this->permissions->permissionActorId($user) < 1) {
            $current = $this->portalAdmin->handle('referral_get', [], $user);
            $cfg = is_array($current['data'] ?? null) ? $current['data'] : [];
            $enabled = ! (bool) ($cfg['referral_enabled'] ?? false);
            $result = $this->portalAdmin->handle('referral_save', [
                'referral_enabled' => $enabled,
                'referral_percent' => (float) ($cfg['referral_percent'] ?? 0),
            ], $user);
            $this->send($ctx, $chatId, (! empty($result['ok']) ? ($enabled ? '✅' : '❌') : '⛔').' referral');

            return true;
        }
        if ($trimmed === $refPercent && $this->permissions->permissionActorId($user) < 1) {
            $this->state->set($user, 'admin_referral_percent', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_referral_percent', $user, 'Send percent'));

            return true;
        }

        $delLifecycle = $this->texts->getForUser('btn.admin.lifecycle_delete', $user, 'Delete rule');
        $editLifecycle = $this->texts->getForUser('btn.admin.lifecycle_edit', $user, 'Edit rule');
        $manualSend = $this->texts->getForUser('btn.admin.marketing_manual', $user, 'Manual send');
        $discountToggle = $this->texts->getForUser('btn.admin.discount_toggle', $user, 'Toggle code');
        $discountEdit = $this->texts->getForUser('btn.admin.discount_edit', $user, 'Edit code');
        $discountRedemptions = $this->texts->getForUser('btn.admin.discount_redemptions', $user, 'Redemptions');
        $lifecycleToggle = $this->texts->getForUser('btn.admin.lifecycle_toggle', $user, 'Toggle rule');

        if ($trimmed === $discountRedemptions) {
            $this->state->set($user, 'admin_discount_redemptions', ['step' => 'code_id']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_delete', $user, 'Send discount id (0 for all)'));

            return true;
        }

        if ($trimmed === $discountToggle) {
            $this->state->set($user, 'admin_discount_toggle', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_toggle', $user, 'Send discount id'));

            return true;
        }
        if ($trimmed === $discountEdit) {
            $this->state->set($user, 'admin_discount_edit', ['step' => 'id']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_delete', $user, 'Send discount id'));

            return true;
        }
        if ($trimmed === $lifecycleToggle) {
            $this->state->set($user, 'admin_lifecycle_toggle', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_run', $user, 'Send rule id'));

            return true;
        }
        if ($trimmed === $editLifecycle) {
            $this->state->set($user, 'admin_lifecycle_edit', ['step' => 'rule_id']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_run', $user, 'Send rule id'));

            return true;
        }
        if ($trimmed === $manualSend) {
            $this->state->set($user, 'admin_marketing_manual', ['step' => 'segment']);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_segment', $user, 'Send segment key'));

            return true;
        }
        if ($trimmed === $delLifecycle) {
            $this->state->set($user, 'admin_lifecycle_delete', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_delete', $user, 'Send rule id'));

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.broadcast_cancel', $user, 'Cancel broadcast')) {
            $this->state->set($user, 'admin_broadcast_cancel', []);
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_broadcast_cancel', $user, 'Send broadcast id'));

            return true;
        }
        if ($trimmed === $this->texts->getForUser('btn.admin.broadcast_run_worker', $user, 'Run broadcast worker')) {
            $result = $this->mutate->applyForUser($user, 'broadcast_run_worker', []);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return true;
        }

        return false;
    }

    public function routeState(BotContext $ctx, SvpUser $user, int $chatId, string $text): void
    {
        $state = $this->state->get($user);
        $trimmed = trim($text);
        $data = $this->state->data($user);

        if ($state === 'admin_discount_code') {
            $step = (string) ($data['step'] ?? 'code');
            if ($step === 'code') {
                $this->state->set($user, 'admin_discount_code', ['step' => 'value', 'code' => $trimmed]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_value', $user, 'Send value'));

                return;
            }
            if ($step === 'value' && is_numeric(str_replace(',', '.', $trimmed))) {
                $result = $this->mutate->applyForUser($user, 'discount_save', [
                    'code' => (string) ($data['code'] ?? ''),
                    'discount_type' => 'percent',
                    'discount_value' => (float) str_replace(',', '.', $trimmed),
                    'active' => 1,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_discount_delete' && $trimmed !== '') {
            $id = (int) preg_replace('/\D/', '', $trimmed);
            $result = $this->mutate->applyForUser($user, 'discount_delete', ['id' => $id]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_discount_redemptions' && $trimmed !== '') {
            $params = [];
            if (is_numeric($trimmed) && (int) $trimmed > 0) {
                $params['code_id'] = (int) $trimmed;
            }
            $result = $this->mutate->applyForUser($user, 'discount_redemptions', $params);
            $this->state->clear($user);
            $rows = is_array($result['rows'] ?? null) ? $result['rows'] : (is_array($result['data']['rows'] ?? null) ? $result['data']['rows'] : []);
            $body = 'Discount redemptions ('.count($rows).")";
            foreach (array_slice($rows, 0, 15) as $row) {
                $r = (array) $row;
                $body .= "\n#".(int) ($r['id'] ?? 0).' user '.(int) ($r['user_id'] ?? 0);
            }
            if ($rows === []) {
                $body .= "\n—";
            }
            $this->send($ctx, $chatId, ! empty($result['ok']) ? $body : $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_lifecycle_new') {
            $step = (string) ($data['step'] ?? 'segment');
            if ($step === 'segment') {
                $this->state->set($user, 'admin_lifecycle_new', ['step' => 'text', 'segment_key' => $trimmed]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_text', $user, 'Rule message text'));

                return;
            }
            if ($step === 'text') {
                $result = $this->mutate->applyForUser($user, 'marketing_rule_save', [
                    'segment_key' => (string) ($data['segment_key'] ?? ''),
                    'message_text' => $trimmed,
                    'active' => 1,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_lifecycle_run' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'marketing_run_rule_now', ['id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_lifecycle_delete' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'marketing_rule_delete', ['id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));

            return;
        }

        if ($state === 'admin_discount_toggle' && is_numeric($trimmed)) {
            $id = (int) $trimmed;
            $row = DB::table('svp_discount_codes')->where('id', $id)->first();
            if ($row) {
                $result = $this->mutate->applyForUser($user, 'discount_save', [
                    'id' => $id,
                    'code' => (string) ($row->code ?? ''),
                    'discount_type' => (string) ($row->discount_type ?? 'percent'),
                    'discount_value' => (float) ($row->discount_value ?? 0),
                    'active' => empty($row->active) ? 1 : 0,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_discount_edit') {
            $step = (string) ($data['step'] ?? 'id');
            if ($step === 'id' && is_numeric($trimmed)) {
                $row = DB::table('svp_discount_codes')->where('id', (int) $trimmed)->first();
                if (! $row) {
                    $this->send($ctx, $chatId, '⛔ not found');

                    return;
                }
                $this->state->set($user, 'admin_discount_edit', [
                    'step' => 'value',
                    'id' => (int) $trimmed,
                    'code' => (string) ($row->code ?? ''),
                    'discount_type' => (string) ($row->discount_type ?? 'percent'),
                ]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_discount_value', $user, 'Send new value'));

                return;
            }
            if ($step === 'value' && is_numeric(str_replace(',', '.', $trimmed))) {
                $result = $this->mutate->applyForUser($user, 'discount_save', [
                    'id' => (int) ($data['id'] ?? 0),
                    'code' => (string) ($data['code'] ?? ''),
                    'discount_type' => (string) ($data['discount_type'] ?? 'percent'),
                    'discount_value' => (float) str_replace(',', '.', $trimmed),
                    'active' => 1,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_lifecycle_toggle' && is_numeric($trimmed)) {
            $id = (int) $trimmed;
            $row = DB::table('svp_marketing_rules')->where('id', $id)->first();
            if ($row) {
                $result = $this->mutate->applyForUser($user, 'marketing_rule_save', [
                    'id' => $id,
                    'segment_key' => (string) ($row->segment_key ?? ''),
                    'message_text' => (string) ($row->message_text ?? ''),
                    'active' => empty($row->active) ? 1 : 0,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_lifecycle_edit') {
            $step = (string) ($data['step'] ?? 'rule_id');
            if ($step === 'rule_id' && is_numeric($trimmed)) {
                $this->state->set($user, 'admin_lifecycle_edit', ['step' => 'text', 'rule_id' => (int) $trimmed]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_text', $user, 'New message text'));

                return;
            }
            if ($step === 'text') {
                $result = $this->mutate->applyForUser($user, 'marketing_rule_save', [
                    'id' => (int) ($data['rule_id'] ?? 0),
                    'message_text' => $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_marketing_manual') {
            $step = (string) ($data['step'] ?? 'segment');
            if ($step === 'segment') {
                $this->state->set($user, 'admin_marketing_manual', ['step' => 'text', 'segment' => $trimmed]);
                $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.prompt_lifecycle_text', $user, 'Message text'));

                return;
            }
            if ($step === 'text') {
                $result = $this->mutate->applyForUser($user, 'marketing_send_manual', [
                    'segment' => (string) ($data['segment'] ?? ''),
                    'text' => $trimmed,
                ]);
                $this->state->clear($user);
                $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
            }

            return;
        }

        if ($state === 'admin_referral_percent' && is_numeric(str_replace(',', '.', $trimmed))) {
            $current = $this->portalAdmin->handle('referral_get', [], $user);
            $cfg = is_array($current['data'] ?? null) ? $current['data'] : [];
            $result = $this->portalAdmin->handle('referral_save', [
                'referral_enabled' => (bool) ($cfg['referral_enabled'] ?? false),
                'referral_percent' => (float) str_replace(',', '.', $trimmed),
            ], $user);
            $this->state->clear($user);
            $this->send($ctx, $chatId, ! empty($result['ok'])
                ? $this->texts->getForUser('msg.admin.mutate_ok', $user, 'Saved')
                : '⛔ save failed');
        }

        if ($state === 'admin_broadcast_cancel' && is_numeric($trimmed)) {
            $result = $this->mutate->applyForUser($user, 'broadcast_cancel', ['broadcast_id' => (int) $trimmed]);
            $this->state->clear($user);
            $this->send($ctx, $chatId, $this->mutate->resultMessage($user, is_array($result) ? $result : ['ok' => false]));
        }
    }

    protected function openReferral(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        if (! $this->permissions->mayCallOp($user, 'referral_manage')) {
            $this->send($ctx, $chatId, $this->texts->getForUser('msg.admin.denied_permission', $user));

            return;
        }
        $ref = $this->portalAdmin->handle('referral_get', [], $user);
        $cfg = is_array($ref['data'] ?? null) ? $ref['data'] : [];
        $enabled = (bool) ($cfg['referral_enabled'] ?? false);
        $percent = (int) ($cfg['referral_percent'] ?? 0);
        $botName = (string) ($cfg['telegram_bot_username'] ?? $this->settings->get('telegram_bot_username', 'bot'));
        $invite = $botName !== '' ? "https://t.me/{$botName}?start=ref_demo" : '—';
        $body = $this->texts->getForUser('msg.admin.tutorial.referral', $user, 'Referral');
        $body .= "\n\n".($enabled ? '✅' : '❌')." enabled\n{$percent}%";
        $body .= "\nInvite: {$invite}";
        $siteAdmin = $this->permissions->permissionActorId($user) < 1;
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->marketingReferralReply($user, $siteAdmin),
        ]);
    }

    protected function openLifecycle(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $rows = DB::table('svp_marketing_rules')->orderByDesc('id')->limit(8)->get();
        $body = $this->texts->getForUser('msg.admin.tutorial.marketing_lifecycle', $user, 'Lifecycle');
        foreach ($rows as $r) {
            $body .= "\n#".(int) $r->id.' '.(string) ($r->segment_key ?? '').' '.(! empty($r->active) ? '✅' : '⏸');
        }
        if ($rows->isEmpty()) {
            $body .= "\n—";
        }
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->marketingLifecycleReply($user),
        ]);
    }

    protected function openDiscounts(BotContext $ctx, int $chatId, SvpUser $user): void
    {
        $rows = DB::table('svp_discount_codes')->orderByDesc('id')->limit(10)->get();
        $body = $this->texts->getForUser('msg.admin.tutorial.discounts', $user, 'Discounts');
        foreach ($rows as $r) {
            $body .= "\n#".(int) $r->id.' '.(string) ($r->code ?? '').' '.(string) ($r->discount_value ?? '');
        }
        if ($rows->isEmpty()) {
            $body .= "\n—";
        }
        $this->send($ctx, $chatId, $body, [
            'reply_markup' => $this->keyboards->marketingDiscountsReply($user),
        ]);
    }

    protected function sectionIntro(SvpUser $user): string
    {
        return $this->texts->getForUser('msg.admin.section.marketing.intro', $user, 'Marketing');
    }
}
