<?php

namespace App\Modules\Core\Bot\Services;

use App\Models\SvpUser;

class AdminNavService
{
    /** @var array<string, array{label_key: string, emoji: string, tabs: list<array{tab: string}>}> */
    protected const SECTIONS = [
        'users' => [
            'label_key' => 'btn.admin.section.users',
            'emoji' => '👥',
            'tabs' => [
                ['tab' => 'users'],
                ['tab' => 'users_bulk'],
                ['tab' => 'broadcast'],
            ],
        ],
        'resellers' => [
            'label_key' => 'btn.admin.section.resellers',
            'emoji' => '🏪',
            'tabs' => [
                ['tab' => 'resellers'],
                ['tab' => 'reseller_reports'],
                ['tab' => 'reseller_bots'],
                ['tab' => 'reseller_xui_panels'],
            ],
        ],
        'marketing' => [
            'label_key' => 'btn.admin.section.marketing',
            'emoji' => '📣',
            'tabs' => [
                ['tab' => 'referral'],
                ['tab' => 'marketing_lifecycle'],
                ['tab' => 'discounts'],
            ],
        ],
        'finance' => [
            'label_key' => 'btn.admin.section.finance',
            'emoji' => '💰',
            'tabs' => [
                ['tab' => 'plans'],
                ['tab' => 'unit_economics'],
                ['tab' => 'cards'],
                ['tab' => 'receipts'],
                ['tab' => 'referral_reports'],
                ['tab' => 'reseller_charge'],
                ['tab' => 'customer_charges'],
            ],
        ],
        'settings' => [
            'label_key' => 'btn.admin.section.settings',
            'emoji' => '⚙️',
            'tabs' => [
                ['tab' => 'monitoring'],
                ['tab' => 'bots'],
                ['tab' => 'plan_cats'],
                ['tab' => 'texts'],
                ['tab' => 'bot_ui'],
                ['tab' => 'xui_panels'],
                ['tab' => 'configs'],
                ['tab' => 'l2tp_servers'],
                ['tab' => 'site_settings'],
                ['tab' => 'proxy'],
                ['tab' => 'relay'],
                ['tab' => 'force_join'],
                ['tab' => 'purge_expired'],
                ['tab' => 'finance'],
                ['tab' => 'backup'],
                ['tab' => 'notifications'],
                ['tab' => 'logs'],
                ['tab' => 'audit'],
                ['tab' => 'reseller_settings'],
            ],
        ],
    ];

    /** @var array<string, string> */
    protected const TAB_BTN_KEYS = [
        'users' => 'btn.admin.users_search',
        'users_bulk' => 'btn.admin.bulk_short',
        'broadcast' => 'btn.admin.broadcast',
        'receipts' => 'btn.admin.receipts',
        'plans' => 'btn.admin.tab.plans',
        'plan_cats' => 'btn.admin.tab.plan_cats',
        'cards' => 'btn.admin.tab.cards',
        'referral' => 'btn.admin.tab.referral',
        'marketing_lifecycle' => 'btn.admin.tab.marketing_lifecycle',
        'discounts' => 'btn.admin.tab.discounts',
        'resellers' => 'btn.admin.tab.resellers',
        'reseller_reports' => 'btn.admin.tab.reseller_reports',
        'reseller_bots' => 'btn.admin.tab.reseller_bots',
        'reseller_xui_panels' => 'btn.admin.tab.reseller_xui_panels',
        'referral_reports' => 'btn.admin.tab.referral_reports',
        'reseller_charge' => 'btn.admin.tab.reseller_charge',
        'customer_charges' => 'btn.admin.tab.customer_charges',
        'unit_economics' => 'btn.admin.tab.unit_economics',
        'monitoring' => 'btn.admin.tab.monitoring',
        'bot_ui' => 'btn.admin.tab.bot_ui',
        'site_settings' => 'btn.admin.tab.site_settings',
        'proxy' => 'btn.admin.tab.proxy',
        'relay' => 'btn.admin.tab.relay',
        'force_join' => 'btn.admin.tab.force_join',
        'purge_expired' => 'btn.admin.tab.purge_expired',
        'finance' => 'btn.admin.tab.finance_settings',
        'notifications' => 'btn.admin.tab.notifications',
        'logs' => 'btn.admin.tab.logs',
        'audit' => 'btn.admin.tab.audit',
        'reseller_settings' => 'btn.admin.tab.reseller_settings',
    ];

    public function __construct(protected TextService $texts) {}

    /** @return list<string> */
    public function sectionIds(): array
    {
        return array_keys(self::SECTIONS);
    }

    /** @return list<string> */
    public function allTabKeys(): array
    {
        $out = [];
        foreach (self::SECTIONS as $def) {
            foreach ($def['tabs'] as $entry) {
                $out[] = (string) $entry['tab'];
            }
        }

        return array_values(array_unique($out));
    }

    /** @param  array<string, bool>  $allowed */
    public function visibleSectionIds(SvpUser $user, array $allowed): array
    {
        $out = [];
        foreach (self::SECTIONS as $secId => $def) {
            foreach ($def['tabs'] as $entry) {
                $tab = (string) $entry['tab'];
                if (! empty($allowed[$tab])) {
                    $out[] = $secId;
                    break;
                }
            }
        }

        return $out;
    }

    /** @param  array<string, bool>  $allowed */
    public function tabsInSection(string $sectionId, SvpUser $user, array $allowed): array
    {
        $sectionId = strtolower(trim($sectionId));
        if (! isset(self::SECTIONS[$sectionId])) {
            return [];
        }
        $seen = [];
        $out = [];
        foreach (self::SECTIONS[$sectionId]['tabs'] as $entry) {
            $tab = (string) $entry['tab'];
            if (isset($seen[$tab])) {
                continue;
            }
            $seen[$tab] = true;
            if (! empty($allowed[$tab])) {
                $out[] = $tab;
            }
        }

        return $out;
    }

    public function sectionLabel(string $sectionId, SvpUser $user): string
    {
        $sectionId = strtolower(trim($sectionId));
        if (! isset(self::SECTIONS[$sectionId])) {
            return '';
        }
        $def = self::SECTIONS[$sectionId];
        $lbl = $this->texts->getForUser((string) $def['label_key'], $user, $sectionId);

        return trim((string) ($def['emoji'] ?? '').' '.$lbl);
    }

    public function tabLabel(string $tabKey, SvpUser $user): string
    {
        $tabKey = strtolower(trim($tabKey));
        $btnKey = self::TAB_BTN_KEYS[$tabKey] ?? '';
        if ($btnKey !== '') {
            return $this->texts->getForUser($btnKey, $user, $tabKey);
        }

        return $tabKey;
    }

    public function introKey(string $kind, string $id): string
    {
        $id = strtolower(trim($id));

        return $kind === 'section'
            ? 'msg.admin.section.'.$id.'.intro'
            : 'msg.admin.tutorial.'.$id;
    }

    public function matchSectionFromText(string $text, SvpUser $user, array $allowed): string
    {
        $text = trim($text);
        foreach ($this->visibleSectionIds($user, $allowed) as $secId) {
            if ($text === $this->sectionLabel($secId, $user)) {
                return $secId;
            }
        }

        return '';
    }

    public function matchTabFromText(string $text, SvpUser $user, array $allowed): string
    {
        $text = trim($text);
        foreach (array_keys($allowed) as $tab) {
            if (empty($allowed[$tab])) {
                continue;
            }
            if ($text === $this->tabLabel($tab, $user)) {
                return $tab;
            }
        }

        return '';
    }

    public function sectionForTab(string $tabKey): string
    {
        $tabKey = strtolower(trim($tabKey));
        foreach (self::SECTIONS as $secId => $def) {
            foreach ($def['tabs'] as $entry) {
                if ($tabKey === (string) $entry['tab']) {
                    return $secId;
                }
            }
        }

        return '';
    }

    public function isAdminNavText(string $text, SvpUser $user, array $allowed): bool
    {
        $text = trim($text);
        if ($text === '') {
            return false;
        }
        if ($this->matchSectionFromText($text, $user, $allowed) !== '') {
            return true;
        }
        if ($this->matchTabFromText($text, $user, $allowed) !== '') {
            return true;
        }
        foreach (['btn.admin.back_panel', 'btn.admin.back_section', 'btn.admin.back_menu', 'btn.admin.send_admin_portal'] as $key) {
            if ($text === $this->texts->getForUser($key, $user)) {
                return true;
            }
        }

        return false;
    }
}
