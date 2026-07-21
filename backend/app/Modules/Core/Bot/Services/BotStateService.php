<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Modules\Core\Bot\BotContext;

class BotStateService
{
    public function get(SvpUser $user): string
    {
        return (string) ($user->state ?? '');
    }

    /** @return array<string, mixed> */
    public function data(SvpUser $user): array
    {
        $raw = $user->state_data;
        if (is_array($raw)) {
            return $raw;
        }
        if (is_string($raw) && $raw !== '') {
            $decoded = json_decode($raw, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    /** @param  array<string, mixed>  $data */
    public function set(SvpUser $user, ?string $state, array $data = []): void
    {
        $user->state = $state;
        $user->state_data = $data;
        $user->save();
    }

    public function clear(SvpUser $user): void
    {
        $this->set($user, null, []);
    }

    /** @param  array<string, mixed>  $data */
    public function mergeData(SvpUser $user, array $data): void
    {
        $this->set($user, $this->get($user), array_merge($this->data($user), $data));
    }

    public function isBlockingState(string $state, string $platform = '', int $fromId = 0): bool
    {
        $st = (string) $state;
        if ($st === '') {
            return false;
        }
        if (in_array($st, ['awaiting_sync_code', 'receipt_upload', 'buy_choose_traffic', 'buy_discount', 'wallet_topup'], true)) {
            return true;
        }
        if (str_starts_with($st, 'buy_')) {
            return true;
        }
        if (str_starts_with($st, 'svc_note_') || str_starts_with($st, 'svc_rename_') || str_starts_with($st, 'svc_addvol_') || str_starts_with($st, 'svc_addusers_') || str_starts_with($st, 'svc_al_')) {
            return true;
        }
        if (str_starts_with($st, 'adm_service_transfer_')) {
            // Admins may continue transfer flow; treat as non-blocking for them.
            if ($platform !== '' && $fromId > 0 && app(AdminGuard::class)->isPlatformAdmin($platform, $fromId)) {
                return false;
            }

            return true;
        }
        if (str_starts_with($st, 'admin_bak_')) {
            return true;
        }
        if (in_array($st, ['admin_find_user', 'admin_dm', 'admin_broadcast'], true)) {
            return true;
        }
        if (str_starts_with($st, 'admin_w_') || str_starts_with($st, 'admin_set_') || str_starts_with($st, 'admin_line_') || str_starts_with($st, 'admin_ns_') || in_array($st, ['admin_txt_edit', 'admin_inb_uid'], true)) {
            return true;
        }

        return false;
    }

    public function isMainMenuReplyText(string $text, ?SvpUser $user = null): bool
    {
        $t = trim($text);
        if ($t === '') {
            return false;
        }
        foreach (app(UiLayoutService::class)->userMainVisibleLabels($user) as $lab) {
            if ($t === $lab) {
                return true;
            }
        }

        return false;
    }

    /**
     * WP interrupt_blocking_state_on_main_menu_text.
     */
    public function interruptBlockingStateOnMainMenuText(BotContext $ctx, int $fromId, SvpUser $user, string $textTrim): bool
    {
        if (! $this->isMainMenuReplyText($textTrim, $user)) {
            return false;
        }
        if ((int) $user->admin_mode && app(AdminGuard::class)->isPlatformAdmin($ctx->platform, $fromId)) {
            $allowed = app(AdminPermissionService::class)->allowedTabs($user);
            if (app(AdminNavService::class)->isAdminNavText($textTrim, $user, $allowed)) {
                $this->clear($user);

                return true;
            }

            return false;
        }
        $st = $this->get($user);
        if (! $this->isBlockingState($st, $ctx->platform, $fromId)) {
            return false;
        }
        $this->clear($user);

        return true;
    }

    /**
     * WP clear_blocking_state_on_callback.
     */
    public function clearBlockingStateOnCallback(BotContext $ctx, int $fromId, SvpUser $user, int $chatId, string $callbackData): bool
    {
        if ($callbackData === 'noop') {
            return false;
        }
        if ((int) $user->admin_mode && app(AdminGuard::class)->isPlatformAdmin($ctx->platform, $fromId)) {
            return false;
        }
        $st = $this->get($user);
        if (! $this->isBlockingState($st, $ctx->platform, $fromId)) {
            return false;
        }
        $this->clear($user);
        $notify = str_starts_with($st, 'svc_') || in_array($st, ['receipt_upload', 'buy_choose_traffic', 'buy_discount'], true);
        if ($notify && $chatId > 0) {
            app(BotRuntime::class)->sendMessage(
                $ctx,
                $chatId,
                app(TextService::class)->getForUser('msg.state.cancelled', $user, 'Cancelled')
            );
        }

        return true;
    }
}
