<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;
use App\Services\SettingsStore;

class AdminKeyboardBuilder
{
    public function __construct(
        protected TextService $texts,
        protected AdminNavService $nav,
        protected AdminPermissionService $permissions,
        protected UiLayoutService $layout,
        protected SettingsStore $settings,
    ) {}

    /** @return array<string, mixed> */
    public function panelMainReply(SvpUser $user): array
    {
        $custom = $this->layout->buildReplyKeyboard('admin_main', $user);
        if ($custom !== null) {
            return $custom;
        }

        $allowed = $this->permissions->allowedTabs($user);
        $rows = [];
        $pair = [];
        foreach ($this->nav->visibleSectionIds($user, $allowed) as $secId) {
            $pair[] = ['text' => $this->nav->sectionLabel($secId, $user)];
            if (count($pair) >= 2) {
                $rows[] = $pair;
                $pair = [];
            }
        }
        if ($pair !== []) {
            $rows[] = $pair;
        }

        $portal = $this->texts->getForUser('btn.admin.send_admin_portal', $user, '');
        if ($portal !== '') {
            $rows[] = [['text' => $portal]];
        }

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function sectionReply(string $sectionId, SvpUser $user): array
    {
        foreach ($this->studioSurfaceCandidates($sectionId) as $surface) {
            $custom = $this->layout->buildReplySubmenuWithBack($surface, $user);
            if ($custom !== null) {
                return $custom;
            }
        }

        $allowed = $this->permissions->allowedTabs($user);
        $rows = [];
        $pair = [];
        foreach ($this->nav->tabsInSection($sectionId, $user, $allowed) as $tab) {
            $pair[] = ['text' => $this->nav->tabLabel($tab, $user)];
            if (count($pair) >= 2) {
                $rows[] = $pair;
                $pair = [];
            }
        }
        if ($pair !== []) {
            $rows[] = $pair;
        }
        $rows[] = [['text' => $this->texts->getForUser('btn.admin.back_panel', $user, '⬅️ Panel')]];

        return [
            'keyboard' => $rows,
            'resize_keyboard' => true,
            'one_time_keyboard' => false,
        ];
    }

    /** @return array<string, mixed> */
    public function inlineReceipt(int $receiptId): array
    {
        return [
            'inline_keyboard' => [[
                ['text' => '✅', 'callback_data' => 'rc:a:'.$receiptId],
                ['text' => '❌', 'callback_data' => 'rc:r:'.$receiptId],
            ]],
        ];
    }

    /**
     * @param  list<object>  $slice
     * @return array<string, mixed>
     */
    public function inlineCatalogList(string $kind, array $slice, int $off, int $total, int $lim = 8): array
    {
        $ent = $kind === 'plans' ? 'pl' : ($kind === 'cards' ? 'cd' : 'pc');
        $rows = [];
        foreach ($slice as $row) {
            $id = (int) ($row->id ?? 0);
            if ($id < 1) {
                continue;
            }
            $on = ! empty($row->active) ? '⏸' : '✅';
            $rows[] = [
                ['text' => $on.' #'.$id, 'callback_data' => 'pnl:cat:t:'.$ent.':'.$id],
                ['text' => '✏️ #'.$id, 'callback_data' => 'pnl:cat:e:'.$ent.':'.$id],
                ['text' => '🗑 #'.$id, 'callback_data' => 'pnl:cat:d:'.$ent.':'.$id],
            ];
        }
        $nav = [];
        if ($off > 0) {
            $nav[] = ['text' => '◀', 'callback_data' => 'pnl:cat:l:'.$kind.':'.max(0, $off - $lim)];
        }
        $nav[] = ['text' => '➕', 'callback_data' => 'pnl:cat:n:'.$ent];
        if ($total > $off + $lim) {
            $nav[] = ['text' => '▶', 'callback_data' => 'pnl:cat:l:'.$kind.':'.($off + $lim)];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }

        return ['inline_keyboard' => $rows];
    }

    /** @return array<string, mixed> */
    public function inlineCatalogPage(string $kind, int $nextOffset): array
    {
        return $this->inlineCatalogList($kind, [], $nextOffset, $nextOffset + 1, 8);
    }

    /** @return array<string, mixed> */
    public function inlineCatalogDeleteConfirm(string $ent, int $id): array
    {
        return [
            'inline_keyboard' => [[
                ['text' => '✅', 'callback_data' => 'pnl:cat:dy:'.$ent.':'.$id],
                ['text' => '❌', 'callback_data' => 'noop'],
            ]],
        ];
    }

    /** @return array<string, mixed> */
    public function inlineReceiptRejectReasons(int $receiptId): array
    {
        $stored = $this->settings->get('receipt_reject_reasons', []);
        $reasons = [];
        if (is_array($stored) && $stored !== []) {
            foreach ($stored as $i => $item) {
                if (is_array($item)) {
                    $key = (string) ($item['key'] ?? $item['id'] ?? 'r'.$i);
                    $label = (string) ($item['label'] ?? $item['text'] ?? $key);
                } else {
                    $key = 'r'.$i;
                    $label = (string) $item;
                }
                if ($label !== '') {
                    $reasons[$key] = $label;
                }
            }
        }
        if ($reasons === []) {
            $reasons = [
                'blur' => 'تار',
                'amount' => 'مبلغ',
                'duplicate' => 'تکراری',
                'other' => 'سایر',
            ];
        }
        $rows = [];
        foreach ($reasons as $key => $label) {
            $cb = 'rc:rr:'.$key.':'.$receiptId;
            if (strlen($cb) <= 64) {
                $rows[] = [['text' => $label, 'callback_data' => $cb]];
            }
        }
        $rows[] = [['text' => '↩️', 'callback_data' => 'rc:rb:'.$receiptId]];

        return ['inline_keyboard' => $rows];
    }

    /** @return array<string, mixed> */
    public function inlineFinanceTabs(SvpUser $user): array
    {
        $allowed = $this->permissions->allowedTabs($user);
        $tabs = [
            'receipts' => '🧾',
            'plans' => '📋',
            'cards' => '💳',
            'customer_charges' => '💰',
            'referral_reports' => '🔗',
            'reseller_charge' => '🏪',
            'unit_economics' => '📊',
        ];
        $rows = [];
        $row = [];
        foreach ($tabs as $tab => $emoji) {
            if (! in_array($tab, $allowed, true)) {
                continue;
            }
            $cb = 'pnl:fin:tab:'.$tab;
            if (strlen($cb) > 64) {
                continue;
            }
            $row[] = [
                'text' => $emoji.' '.$this->nav->tabLabel($tab, $user),
                'callback_data' => $cb,
            ];
            if (count($row) >= 2) {
                $rows[] = $row;
                $row = [];
            }
        }
        if ($row !== []) {
            $rows[] = $row;
        }

        return ['inline_keyboard' => $rows];
    }

    /** @return array<string, mixed> */
    public function usersSubmenuReply(SvpUser $user): array
    {
        $custom = $this->layout->buildReplySubmenuWithBack('admin_users_submenu', $user);
        if ($custom !== null) {
            return $custom;
        }

        return [
            'keyboard' => [
                [
                    ['text' => $this->texts->getForUser('btn.admin.users_pending', $user, 'Pending')],
                    ['text' => $this->texts->getForUser('btn.admin.users_approved', $user, 'Approved')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.users_rejected', $user, 'Rejected')],
                    ['text' => $this->texts->getForUser('btn.admin.users_search', $user, 'Search')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.user_manual_create', $user, 'Create user')],
                    ['text' => $this->texts->getForUser('btn.admin.user_merge', $user, 'Merge users')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.user_set_role', $user, 'Set role')],
                    ['text' => $this->texts->getForUser('btn.admin.user_set_referrer', $user, 'Set referrer')],
                ],
                [['text' => $this->texts->getForUser('btn.admin.back_panel', $user, 'Panel')]],
            ],
            'resize_keyboard' => true,
        ];
    }

    /**
     * @param  list<object>  $list
     * @return array<string, mixed>
     */
    public function inlineUsersQueue(string $queue, array $list, int $off, int $total, int $lim = 5): array
    {
        $rows = [];
        foreach ($list as $row) {
            $uid = (int) ($row->id ?? 0);
            if ($uid < 1) {
                continue;
            }
            $label = (string) ($row->username ?? $row->tg_username ?? '#'.$uid);
            $line = [['text' => '👤 '.$label, 'callback_data' => 'pnl:ui:'.$uid]];
            if ($queue === 'rejected') {
                $line[] = ['text' => '↩', 'callback_data' => 'pnl:rr:'.$uid];
            }
            $rows[] = $line;
        }
        $nav = [];
        if ($off > 0) {
            $prevCb = match ($queue) {
                'approved' => 'pnl:aq:'.max(0, $off - $lim),
                'rejected' => 'pnl:rq:'.max(0, $off - $lim),
                default => 'pnl:pq:'.max(0, $off - $lim),
            };
            $nav[] = ['text' => '◀', 'callback_data' => $prevCb];
        }
        if ($total > $off + $lim) {
            $nextCb = match ($queue) {
                'approved' => 'pnl:aq:'.($off + $lim),
                'rejected' => 'pnl:rq:'.($off + $lim),
                default => 'pnl:pq:'.($off + $lim),
            };
            $nav[] = ['text' => '▶', 'callback_data' => $nextCb];
        }
        if ($nav !== []) {
            $rows[] = $nav;
        }
        $rows[] = [
            ['text' => 'Pending', 'callback_data' => 'pnl:pq:0'],
            ['text' => 'Approved', 'callback_data' => 'pnl:aq:0'],
            ['text' => 'Rejected', 'callback_data' => 'pnl:rq:0'],
        ];

        return ['inline_keyboard' => $rows];
    }

    /**
     * Admin create-service payment mode (pnl:nsx fixed / pnl:nsm per-GB).
     *
     * @return array{inline_keyboard: list<list<array<string, string>>>}
     */
    public function adminCreateServiceModeKeyboard(int $targetUid, int $planId, ?int $volumeGb = null): array
    {
        $t = $targetUid;
        $p = $planId;
        $modes = ['w' => '💳 Wallet', 'f' => '🎁 Free', 'i' => '🧾 Invoice'];
        $rows = [];
        foreach ($modes as $k => $lab) {
            $cb = $volumeGb === null
                ? 'pnl:nsx:'.$t.':'.$p.':'.$k
                : 'pnl:nsm:'.$t.':'.$p.':'.(int) $volumeGb.':'.$k;
            if (strlen($cb) <= 64) {
                $rows[] = [['text' => $lab, 'callback_data' => $cb]];
            }
        }

        return ['inline_keyboard' => $rows];
    }

    /**
     * Admin renew / add-volume / add-slots payment mode (pnl:nrr / nva / nus).
     *
     * @param  'renew'|'vol'|'slots'  $kind
     * @return array{inline_keyboard: list<list<array<string, string>>>}
     */
    public function adminServicePaymentModeKeyboard(string $kind, int $serviceId, ?int $extra = null): array
    {
        $sid = $serviceId;
        $modes = ['w' => '💳 Wallet', 'f' => '🎁 Free', 'i' => '🧾 Invoice'];
        $rows = [];
        foreach ($modes as $k => $lab) {
            $cb = match ($kind) {
                'renew' => 'pnl:nrr:'.$sid.':'.$k,
                'vol' => 'pnl:nva:'.$sid.':'.(int) $extra.':'.$k,
                default => 'pnl:nus:'.$sid.':'.(int) $extra.':'.$k,
            };
            if (strlen($cb) <= 64) {
                $rows[] = [['text' => $lab, 'callback_data' => $cb]];
            }
        }

        return ['inline_keyboard' => $rows];
    }

    /** @return array<string, mixed> */
    public function inlineBroadcastSegments(): array
    {
        return [
            'inline_keyboard' => [
                [
                    ['text' => 'Telegram', 'callback_data' => 'pnl:bc:tg'],
                    ['text' => 'Bale', 'callback_data' => 'pnl:bc:bl'],
                ],
                [
                    ['text' => 'Both', 'callback_data' => 'pnl:bc:both'],
                ],
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function marketingReferralReply(SvpUser $user, bool $siteAdmin): array
    {
        $rows = [];
        if ($siteAdmin) {
            $rows[] = [
                ['text' => $this->texts->getForUser('btn.admin.referral_toggle', $user, 'Toggle')],
                ['text' => $this->texts->getForUser('btn.admin.referral_percent', $user, 'Percent')],
            ];
        }

        return ['keyboard' => $rows !== [] ? $rows : [['text' => $this->texts->getForUser('btn.admin.back_panel', $user, 'Panel')]], 'resize_keyboard' => true];
    }

    /** @return array<string, mixed> */
    public function marketingDiscountsReply(SvpUser $user): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => $this->texts->getForUser('btn.admin.discount_new', $user, 'New code')],
                    ['text' => $this->texts->getForUser('btn.admin.discount_delete', $user, 'Delete')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.discount_toggle', $user, 'Toggle code')],
                    ['text' => $this->texts->getForUser('btn.admin.discount_edit', $user, 'Edit code')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.discount_redemptions', $user, 'Redemptions')],
                ],
                [['text' => $this->texts->getForUser('btn.admin.back_panel', $user, 'Panel')]],
            ],
            'resize_keyboard' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function marketingLifecycleReply(SvpUser $user): array
    {
        return [
            'keyboard' => [
                [
                    ['text' => $this->texts->getForUser('btn.admin.lifecycle_new', $user, 'New rule')],
                    ['text' => $this->texts->getForUser('btn.admin.lifecycle_run', $user, 'Run now')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.lifecycle_delete', $user, 'Delete rule')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.lifecycle_edit', $user, 'Edit rule')],
                    ['text' => $this->texts->getForUser('btn.admin.lifecycle_toggle', $user, 'Toggle rule')],
                ],
                [
                    ['text' => $this->texts->getForUser('btn.admin.marketing_manual', $user, 'Manual send')],
                ],
                [['text' => $this->texts->getForUser('btn.admin.back_panel', $user, 'Panel')]],
            ],
            'resize_keyboard' => true,
        ];
    }

    /** @return array<string, mixed> */
    public function inlineReceiptPage(int $nextOffset): array
    {
        $cb = 'pnl:rcp:p:'.$nextOffset;
        if (strlen($cb) > 64) {
            return ['inline_keyboard' => []];
        }

        return [
            'inline_keyboard' => [[
                ['text' => '📄 Next', 'callback_data' => $cb],
            ]],
        ];
    }

    /** @return list<string> */
    protected function studioSurfaceCandidates(string $sectionId): array
    {
        $sectionId = strtolower(trim($sectionId));

        return match ($sectionId) {
            'users' => ['admin_users_submenu'],
            'finance' => ['admin_finance_submenu'],
            default => [],
        };
    }
}
