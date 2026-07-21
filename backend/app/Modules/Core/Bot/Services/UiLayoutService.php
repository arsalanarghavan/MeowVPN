<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpService;
use App\Models\SvpUser;
use App\Modules\Core\Services\Portal\PortalLinkService;
use App\Services\SettingsStore;

/**
 * Runtime Bot UI layout builder (WP SimpleVPBot_Keyboards + UI_Layout parity).
 */
class UiLayoutService
{
    public const COPY_TEXT_MAX_LEN = 256;

    public function __construct(
        protected SettingsStore $settings,
        protected TextService $texts,
        protected UiLayoutStudioService $studio,
        protected PortalLinkService $portalLinks,
    ) {}

    /** @return array<string, mixed>|null */
    public function buildReplyKeyboard(string $layoutKey, ?SvpUser $user): ?array
    {
        $layout = $this->settings->get('bot_ui_layout', []);
        if (is_array($layout) && isset($layout[$layoutKey]) && is_array($layout[$layoutKey]) && $layout[$layoutKey] !== []) {
            return [
                'keyboard' => $layout[$layoutKey],
                'resize_keyboard' => true,
                'one_time_keyboard' => false,
            ];
        }

        $rows = $this->replyRowsFromEffectiveSurface($layoutKey, $user);
        if ($rows === []) {
            return null;
        }

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @return array<string, mixed>|null */
    public function buildReplySubmenuWithBack(string $surface, ?SvpUser $user, ?string $backLabel = null): ?array
    {
        $keyboard = $this->buildReplyKeyboard($surface, $user);
        if ($keyboard === null) {
            return null;
        }

        $rows = is_array($keyboard['keyboard'] ?? null) ? $keyboard['keyboard'] : [];
        $back = trim((string) ($backLabel ?? ''));
        if ($back === '') {
            $back = $user
                ? $this->texts->getForUser('btn.admin.back_panel', $user, '⬅️ Panel')
                : $this->texts->get('btn.admin.back_panel', '⬅️ Panel');
        }
        $rows[] = [['text' => $back]];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @return array<string, mixed>|null */
    public function buildUserCustomSubmenuWithBack(string $surface, ?SvpUser $user): ?array
    {
        $keyboard = $this->buildReplyKeyboard($surface, $user);
        if ($keyboard === null) {
            return null;
        }

        $rows = is_array($keyboard['keyboard'] ?? null) ? $keyboard['keyboard'] : [];
        $back = $user
            ? $this->texts->getForUser('btn.main.back_menu', $user, '⬅️ Menu')
            : $this->texts->get('btn.main.back_menu', '⬅️ Menu');
        $rows[] = [['text' => $back]];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @return list<string> */
    public function userMainVisibleLabels(?SvpUser $user = null): array
    {
        $labels = [];
        foreach ($this->effectiveRowsForSurface('user_main', $user) as $row) {
            foreach ($row as $cell) {
                if (empty($cell['enabled'])) {
                    continue;
                }
                $aid = (string) ($cell['id'] ?? '');
                if ($aid === '') {
                    continue;
                }
                $label = UiActionRegistryService::reply_button_text(
                    $aid,
                    $user,
                    ! empty($cell['glass']),
                );
                if ($label !== '') {
                    $labels[] = $label;
                }
            }
        }

        return array_values(array_unique($labels));
    }

    public function isUserBackToMainText(string $text, ?SvpUser $user): bool
    {
        $back = $user
            ? $this->texts->getForUser('btn.main.back_menu', $user, '')
            : $this->texts->get('btn.main.back_menu', '');

        return trim($text) === trim($back) && trim($back) !== '';
    }

    public function matchUserReplyAction(string $text, ?SvpUser $user, ?string $surface = null): ?string
    {
        $surfaces = ['user_main'];
        if ($surface === null) {
            foreach (UiCustomGroupsService::get_all() as $group) {
                if (($group['section'] ?? '') === 'user') {
                    $sid = (string) ($group['surface_id'] ?? '');
                    if ($sid !== '') {
                        $surfaces[] = $sid;
                    }
                }
            }
        } else {
            $surfaces = [$surface];
        }

        foreach ($surfaces as $sid) {
            if ($sid === '') {
                continue;
            }
            foreach ($this->effectiveRowsForSurface($sid, $user) as $row) {
                foreach ($row as $cell) {
                    if (empty($cell['enabled'])) {
                        continue;
                    }
                    $aid = (string) ($cell['id'] ?? '');
                    if ($aid === '') {
                        continue;
                    }
                    if (UiActionRegistryService::text_matches_reply_action(
                        $text,
                        $user,
                        $aid,
                        ! empty($cell['glass']),
                    )) {
                        return $aid;
                    }
                }
            }
        }

        return null;
    }

    public function matchCustomHubSubmenuSurface(string $text, ?SvpUser $user, string $section): ?string
    {
        foreach (UiCustomGroupsService::groupParentSurfaces($section) as $parent) {
            foreach ($this->effectiveRowsForSurface($parent, $user) as $row) {
                foreach ($row as $cell) {
                    if (empty($cell['enabled'])) {
                        continue;
                    }
                    $aid = (string) ($cell['id'] ?? '');
                    if ($aid === '') {
                        continue;
                    }
                    if (! UiActionRegistryService::text_matches_reply_action(
                        $text,
                        $user,
                        $aid,
                        ! empty($cell['glass']),
                    )) {
                        continue;
                    }
                    $def = UiActionRegistryService::get($aid);
                    $route = is_array($def['route'] ?? null) ? $def['route'] : [];
                    if ($section === 'user' && isset($route['user_submenu'])) {
                        return (string) $route['user_submenu'];
                    }
                    if ($section === 'admin' && isset($route['admin_submenu'])) {
                        return (string) $route['admin_submenu'];
                    }
                }
            }
        }

        return null;
    }

    /**
     * @return list<list<array<string, mixed>>>
     */
    public function effectiveRowsForSurface(string $surface, ?SvpUser $user = null): array
    {
        unset($user);
        $rows = $this->studio->getMergedSurfaces()[$surface] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        if (UiActionRegistryService::l2tpEnabled()) {
            return $rows;
        }

        $filtered = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $built = [];
            foreach ($row as $cell) {
                if (! is_array($cell)) {
                    continue;
                }
                $id = (string) ($cell['id'] ?? '');
                if ($id === 'admin.cat.l2tp' || str_starts_with($id, 'svc_l2tp.')) {
                    continue;
                }
                $built[] = $cell;
            }
            if ($built !== []) {
                $filtered[] = $built;
            }
        }

        return $filtered;
    }

    /**
     * @return list<list<array{text: string}>>
     */
    protected function replyRowsFromEffectiveSurface(string $surface, ?SvpUser $user): array
    {
        $out = [];
        foreach ($this->effectiveRowsForSurface($surface, $user) as $line) {
            $row = [];
            foreach ($line as $cell) {
                if (! is_array($cell) || empty($cell['enabled'])) {
                    continue;
                }
                $aid = (string) ($cell['id'] ?? '');
                if ($aid === '') {
                    continue;
                }
                $def = UiActionRegistryService::get($aid);
                if (! $def || ($def['kind'] ?? '') !== 'reply') {
                    continue;
                }
                $text = UiActionRegistryService::reply_button_text(
                    $aid,
                    $user,
                    ! empty($cell['glass']),
                );
                if ($text !== '') {
                    $row[] = ['text' => $text];
                }
            }
            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /**
     * Inline service menu from bot_ui_layout surfaces svc_menu_xray / svc_menu_l2tp
     * (slot ids svc_xray.* / svc_l2tp.* — WP class-keyboards.php).
     *
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    public function buildInlineServiceMenu(
        int $serviceId,
        string $platform,
        ?SvpUser $user = null,
        bool $isL2tp = false,
        bool $showAdminSoftDelete = false,
        bool $showPanelToggle = false,
    ): array {
        $id = max(0, $serviceId);
        $uid = (int) ($user?->id ?? 0);
        $portal = $this->buildServicePortalUrl($uid, $id);

        if ($isL2tp) {
            return ['inline_keyboard' => $this->rowsFromSurface(
                'svc_menu_l2tp',
                fn (string $slot, array $cell) => $this->svcL2tpSlotButton($slot, $id, $portal, $user, $showAdminSoftDelete),
                $user,
            )];
        }

        $priceSlots = (float) $this->settings->get('price_per_extra_user', 0) > 0;

        return ['inline_keyboard' => $this->rowsFromSurface(
            'svc_menu_xray',
            function (string $slot, array $cell) use ($id, $platform, $portal, $user, $showAdminSoftDelete, $showPanelToggle, $priceSlots) {
                if ($slot === 'svc_xray.slots' && ! $priceSlots) {
                    return [];
                }
                if ($slot === 'svc_xray.del_admin' && ! $showAdminSoftDelete) {
                    return [];
                }
                if ($slot === 'svc_xray.panel_toggle' && ! $showPanelToggle) {
                    return [];
                }

                return $this->svcXraySlotButton($slot, $id, $platform, $portal, $user);
            },
            $user,
        )];
    }

    /**
     * Post-purchase QR keyboard from purchase_delivery_qr (slots purchase_qr.*).
     *
     * @return array{inline_keyboard: list<list<array<string, mixed>>>}
     */
    public function buildPurchaseQrKeyboard(int $serviceId, ?SvpUser $user = null, string $portalUrl = ''): array
    {
        $rows = $this->rowsFromSurface(
            'purchase_delivery_qr',
            fn (string $slot, array $cell) => $this->purchaseQrSlotButton($slot, $serviceId, $user),
            $user,
        );
        $pu = trim($portalUrl);
        if ($pu !== '') {
            $rows[] = [[
                'text' => $this->btn('btn.common.web_panel', $user, 'Web panel'),
                'url' => $pu,
            ]];
        }

        return ['inline_keyboard' => $rows];
    }

    /**
     * Post-purchase configs rows: copy-all + per-URI buttons (svc:ca / svc:w).
     *
     * @param  array{uris?: list<string>, config_uris?: list<string>, labels?: list<string>, config_labels?: list<string>}  $data
     * @return list<list<array<string, mixed>>>
     */
    public function buildPurchaseConfigsRows(int $serviceId, array $data, ?SvpUser $user = null): array
    {
        $id = max(0, $serviceId);
        $uris = $data['config_uris'] ?? $data['uris'] ?? [];
        $labels = $data['config_labels'] ?? $data['labels'] ?? [];
        if (! is_array($uris)) {
            $uris = [];
        }
        if (! is_array($labels)) {
            $labels = [];
        }

        $copyLines = [];
        foreach ($uris as $u) {
            $u = trim((string) $u);
            if ($u !== '') {
                $copyLines[] = $u;
            }
        }
        $copyAll = implode("\n", $copyLines);
        $rows = [];

        foreach ($this->effectiveRows('purchase_delivery_configs') as $line) {
            foreach ($line as $cell) {
                if (empty($cell['enabled'])) {
                    continue;
                }
                $slot = (string) ($cell['id'] ?? '');
                if ($slot !== 'purchase_cfg.copy_all' || $copyAll === '') {
                    continue;
                }
                $label = $this->btn('btn.purchase.delivery.copy_all', $user, 'Copy all');
                if (strlen($copyAll) <= self::COPY_TEXT_MAX_LEN) {
                    $btn = ['text' => $label, 'copy_text' => ['text' => $copyAll]];
                } else {
                    $cb = 'svc:ca:'.$id;
                    if (strlen($cb) > 64) {
                        continue;
                    }
                    $btn = ['text' => $label, 'callback_data' => $cb];
                }
                $rows[] = [$this->decorateButton($btn, $cell)];
            }
        }

        $nUri = count($uris);
        $idx = 0;
        foreach ($uris as $i => $u) {
            $u = trim((string) $u);
            if ($u === '') {
                $idx++;

                continue;
            }
            if ($idx >= 20) {
                break;
            }
            $remark = isset($labels[$i]) ? trim((string) $labels[$i]) : '';
            if ($remark === '') {
                $remark = $nUri > 1 ? ('Config '.($idx + 1)) : 'Config';
            }
            $lbl = $this->truncateLabel($remark, 30);
            if (strlen($u) <= self::COPY_TEXT_MAX_LEN) {
                $btn = ['text' => $lbl, 'copy_text' => ['text' => $u]];
            } elseif ($id > 0) {
                $cb = 'svc:w:'.$id.':'.(int) $i;
                if (strlen($cb) > 64) {
                    $idx++;

                    continue;
                }
                $btn = ['text' => $lbl, 'callback_data' => $cb];
            } else {
                $idx++;

                continue;
            }
            $rows[] = [$btn];
            $idx++;
        }

        return $rows;
    }

    /**
     * Panel enable/disable button for platform admins (svc:pe).
     *
     * @return array<string, string>
     */
    public function panelToggleButton(int $serviceId, ?SvpUser $user = null): array
    {
        $id = max(0, $serviceId);
        if ($id < 1) {
            return [];
        }
        $svc = SvpService::query()->find($id);
        if (! $svc || (string) ($svc->service_type ?? '') === 'l2tp') {
            return [];
        }
        $enabled = (int) ($svc->panel_client_enabled ?? 1) === 1;
        $key = $enabled ? 'btn.svc.panel_disable' : 'btn.svc.panel_enable';

        return [
            'text' => $this->btn($key, $user, $enabled ? 'Disable panel' : 'Enable panel'),
            'callback_data' => 'svc:pe:'.$id,
        ];
    }

    /**
     * @param  callable(string, array<string, mixed>): array<string, mixed>  $slotFn
     * @return list<list<array<string, mixed>>>
     */
    protected function rowsFromSurface(string $surface, callable $slotFn, ?SvpUser $user): array
    {
        $rows = [];
        foreach ($this->effectiveRows($surface) as $line) {
            $rline = [];
            foreach ($line as $cell) {
                if (! is_array($cell) || empty($cell['enabled'])) {
                    continue;
                }
                $slot = (string) ($cell['id'] ?? '');
                if ($slot === '') {
                    continue;
                }
                $b = $slotFn($slot, $cell);
                if ($b !== []) {
                    $rline[] = $this->decorateButton($b, $cell);
                }
            }
            if ($rline !== []) {
                $rows[] = $rline;
            }
        }

        return $rows;
    }

    /**
     * @return list<list<array<string, mixed>>>
     */
    protected function effectiveRows(string $surface): array
    {
        return $this->effectiveRowsForSurface($surface);
    }

    /**
     * @param  list<list<array<string, mixed>|string>>  $cells
     * @return list<list<array{text: string}>>
     */
    protected function replyRowsFromSurfaceCells(array $cells, ?SvpUser $user): array
    {
        $out = [];
        foreach ($cells as $line) {
            if (! is_array($line)) {
                continue;
            }
            $row = [];
            foreach ($line as $cell) {
                if (is_string($cell)) {
                    $cell = ['id' => $cell, 'enabled' => true];
                }
                if (! is_array($cell) || empty($cell['enabled'])) {
                    continue;
                }
                $text = trim((string) ($cell['text'] ?? ''));
                if ($text === '' && isset($cell['id'])) {
                    $text = (string) ($cell['id'] ?? '');
                }
                if ($text !== '') {
                    $row[] = ['text' => $text];
                }
            }
            if ($row !== []) {
                $out[] = $row;
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    protected function svcXraySlotButton(string $slot, int $id, string $platform, string $portal, ?SvpUser $user): array
    {
        return match ($slot) {
            'svc_xray.panel' => [
                'text' => $this->btn('btn.service.show_panel', $user, 'Panel'),
                'callback_data' => 'svc:p:'.$id,
            ],
            'svc_xray.usage' => [
                'text' => $this->btn('btn.svc.show_usage', $user, 'Usage'),
                'callback_data' => 'svc:us:'.$id,
            ],
            'svc_xray.config' => [
                'text' => $this->btn('btn.svc.config_qr', $user, 'Config / QR'),
                'callback_data' => 'svc:l:'.$id,
            ],
            'svc_xray.regen' => [
                'text' => $this->btn('btn.svc.regenerate_key', $user, 'Regen key'),
                'callback_data' => 'svc:k:'.$id,
            ],
            'svc_xray.regen_sub' => [
                'text' => $this->btn('btn.svc.regenerate_sub_id', $user, 'Regen sub'),
                'callback_data' => 'svc:rs:'.$id,
            ],
            'svc_xray.panel_toggle' => $this->panelToggleButton($id, $user),
            'svc_xray.refresh' => [
                'text' => $this->btn('btn.svc.update_servers', $user, 'Update servers'),
                'callback_data' => 'svc:u:'.$id,
            ],
            'svc_xray.renew' => [
                'text' => $this->btn('btn.svc.renew_short', $user, 'Renew'),
                'callback_data' => 'svc:r:'.$id,
            ],
            'svc_xray.volume' => [
                'text' => $this->btn('btn.svc.add_volume', $user, '+GB'),
                'callback_data' => 'svc:v:'.$id,
            ],
            'svc_xray.slots' => [
                'text' => $this->btn('btn.svc.add_users', $user, '+Users'),
                'callback_data' => 'svc:sl:'.$id,
            ],
            'svc_xray.rename' => [
                'text' => $this->btn('btn.svc.rename', $user, 'Rename'),
                'callback_data' => 'svc:rn:'.$id,
            ],
            'svc_xray.note' => [
                'text' => $this->btn('btn.svc.panel_note', $user, 'Note'),
                'callback_data' => 'svc:n:'.$id,
            ],
            'svc_xray.alerts' => [
                'text' => $this->btn('btn.svc.alerts', $user, 'Alerts'),
                'callback_data' => 'svc:al:'.$id,
            ],
            'svc_xray.ip' => [
                'text' => $this->btn('btn.svc.active_connections', $user, 'Connections'),
                'callback_data' => 'svc:ip:'.$id,
            ],
            'svc_xray.faq' => [
                'text' => $this->btn('btn.svc.faq_short', $user, 'FAQ'),
                'callback_data' => 'svc:f:'.$id,
            ],
            'svc_xray.transfer' => [
                'text' => $this->btn('btn.svc.transfer', $user, 'Transfer'),
                'callback_data' => 'svc:tx:'.$id,
            ],
            'svc_xray.support' => [
                'text' => $this->btn('btn.svc.support', $user, 'Support'),
                'callback_data' => 'svc:su:'.$id,
            ],
            'svc_xray.back' => [
                'text' => $this->btn('btn.common.back', $user, 'Back'),
                'callback_data' => 'svc:b:'.$id,
            ],
            'svc_xray.del_admin' => $this->adminSoftDeleteButton($id, $user),
            default => [],
        };
    }

    /** @return array<string, mixed> */
    protected function svcL2tpSlotButton(string $slot, int $id, string $portal, ?SvpUser $user, bool $showAdminSoftDelete): array
    {
        return match ($slot) {
            'svc_l2tp.conn' => [
                'text' => $this->btn('btn.svc.show_connection', $user, 'Connection'),
                'callback_data' => 'svc:p:'.$id,
            ],
            'svc_l2tp.usage' => [
                'text' => $this->btn('btn.svc.show_usage', $user, 'Usage'),
                'callback_data' => 'svc:us:'.$id,
            ],
            'svc_l2tp.portal' => $portal !== ''
                ? ['text' => $this->btn('btn.common.web_panel', $user, 'Web panel'), 'url' => $portal]
                : [],
            'svc_l2tp.pass' => [
                'text' => $this->btn('btn.svc.change_password', $user, 'Change password'),
                'callback_data' => 'svc:k:'.$id,
            ],
            'svc_l2tp.renew' => [
                'text' => $this->btn('btn.svc.renew', $user, 'Renew'),
                'callback_data' => 'svc:r:'.$id,
            ],
            'svc_l2tp.autorenew' => [
                'text' => $this->btn('btn.svc.auto_renew', $user, 'Auto-renew'),
                'callback_data' => 'svc:ar:'.$id,
            ],
            'svc_l2tp.alerts' => [
                'text' => $this->btn('btn.svc.alerts', $user, 'Alerts'),
                'callback_data' => 'svc:al:'.$id,
            ],
            'svc_l2tp.rename' => [
                'text' => $this->btn('btn.svc.rename', $user, 'Rename'),
                'callback_data' => 'svc:rn:'.$id,
            ],
            'svc_l2tp.faq' => [
                'text' => $this->btn('btn.svc.faq', $user, 'FAQ'),
                'callback_data' => 'svc:f:'.$id,
            ],
            'svc_l2tp.support' => [
                'text' => $this->btn('btn.svc.support', $user, 'Support'),
                'callback_data' => 'svc:su:'.$id,
            ],
            'svc_l2tp.transfer' => [
                'text' => $this->btn('btn.svc.transfer', $user, 'Transfer'),
                'callback_data' => 'svc:tx:'.$id,
            ],
            'svc_l2tp.del_admin' => $showAdminSoftDelete ? $this->adminSoftDeleteButton($id, $user) : [],
            'svc_l2tp.back' => [
                'text' => $this->btn('btn.common.back', $user, 'Back'),
                'callback_data' => 'svc:b:'.$id,
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    protected function purchaseQrSlotButton(string $slot, int $serviceId, ?SvpUser $user): array
    {
        $id = max(0, $serviceId);
        if ($id < 1) {
            return [];
        }

        return match ($slot) {
            'purchase_qr.tutorial' => [
                'text' => $this->btn('btn.purchase.delivery.tutorial', $user, 'Tutorial'),
                'callback_data' => 'svc:pqt:'.$id,
            ],
            'purchase_qr.faq' => [
                'text' => $this->btn('btn.purchase.delivery.faq', $user, 'FAQ'),
                'callback_data' => 'svc:pqf:'.$id,
            ],
            'purchase_qr.support' => [
                'text' => $this->btn('btn.purchase.delivery.support', $user, 'Support'),
                'callback_data' => 'svc:pqs:'.$id,
            ],
            default => [],
        };
    }

    /** @return array<string, string> */
    protected function adminSoftDeleteButton(int $id, ?SvpUser $user): array
    {
        $cb = 'pnl:svc_del:'.$id;
        if (strlen($cb) > 64) {
            return [];
        }

        return [
            'text' => $this->btn('btn.admin.delete_service_soft', $user, 'Soft delete'),
            'callback_data' => $cb,
        ];
    }

    /**
     * @param  array<string, mixed>  $btn
     * @param  array<string, mixed>  $cell
     * @return array<string, mixed>
     */
    protected function decorateButton(array $btn, array $cell): array
    {
        $style = $this->studio->normalizeCellStyle($cell['style'] ?? '');
        if ($style !== '') {
            $btn['style'] = $style;
        }
        $emoji = $this->studio->normalizeCellIconCustomEmojiId($cell['icon_custom_emoji_id'] ?? '');
        if ($emoji !== '') {
            $btn['icon_custom_emoji_id'] = $emoji;
        }

        return $btn;
    }

    protected function btn(string $key, ?SvpUser $user, string $fallback): string
    {
        return $user
            ? $this->texts->getForUser($key, $user, $fallback)
            : $this->texts->get($key, $fallback);
    }

    protected function truncateLabel(string $label, int $max = 30): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }
        if (mb_strlen($label, 'UTF-8') > $max) {
            return mb_substr($label, 0, max(1, $max - 1), 'UTF-8').'…';
        }

        return $label;
    }

    protected function buildServicePortalUrl(int $userId, int $serviceId): string
    {
        if ($userId < 1 || $serviceId < 1) {
            return '';
        }
        $link = $this->portalLinks->buildPortalLink($userId, PortalLinkService::CUSTOMER_TTL, $serviceId);

        return url('/info').'?'.http_build_query([
            'svp_p' => '1',
            'svp_u' => $link['svp_u'],
            'svp_sid' => $serviceId,
            'svp_e' => $link['svp_e'],
            'svp_s' => $link['svp_s'],
        ]);
    }
}
