<?php

namespace App\Services;

class NavTabsBuilder
{
    /** @return list<array{key: string, label: string}> */
    public function build(bool $l2tpEnabled = true): array
    {
        $modules = svp_modules();
        $tabs = [
            ['key' => 'dashboard', 'label' => 'پیشخوان'],
            ['key' => 'monitoring', 'label' => 'مانیتورینگ'],
            ['key' => 'site_settings', 'label' => 'تنظیمات سایت'],
        ];
        if ($modules->isEnabled('telegram') || $modules->isEnabled('bale')) {
            $tabs[] = ['key' => 'bots', 'label' => 'ربات‌ها'];
            $tabs[] = ['key' => 'bot_ui', 'label' => 'استودیوی UI ربات'];
        }
        if ($modules->isEnabled('xui_panel')) {
            $tabs[] = ['key' => 'xui_panels', 'label' => 'پنل‌های 3x-ui'];
            $tabs[] = ['key' => 'configs', 'label' => 'کانفیگ‌ها'];
            $tabs[] = ['key' => 'unit_economics', 'label' => 'اقتصاد واحد'];
        }
        $tabs = array_merge($tabs, [
            ['key' => 'plan_cats', 'label' => 'دسته‌های خرید'],
            ['key' => 'plans', 'label' => 'پلن‌ها'],
            ['key' => 'cards', 'label' => 'کارت‌ها'],
        ]);

        if ($l2tpEnabled && $modules->isEnabled('l2tp')) {
            $tabs[] = ['key' => 'l2tp_servers', 'label' => 'سرورهای L2TP'];
        }

        $tabs = array_merge($tabs, [
            ['key' => 'receipts', 'label' => 'رسیدها'],
            ['key' => 'texts', 'label' => 'متن‌ها'],
            ['key' => 'users', 'label' => 'کاربران'],
            ['key' => 'users_bulk', 'label' => 'عملیات گروهی'],
        ]);
        if ($modules->isEnabled('marketing')) {
            $tabs[] = ['key' => 'broadcast', 'label' => 'پیام همگانی'];
        }
        if ($modules->isEnabled('backup')) {
            $tabs[] = ['key' => 'backup', 'label' => 'بکاپ'];
        }
        $tabs = array_merge($tabs, [
            ['key' => 'referral', 'label' => 'ریفرال و لینک ربات'],
            ['key' => 'referral_reports', 'label' => 'گزارشات رفرال'],
            ['key' => 'reseller_reports', 'label' => 'گزارشات نمایندگان'],
        ]);
        if ($modules->isEnabled('marketing')) {
            $tabs[] = ['key' => 'marketing_lifecycle', 'label' => 'بازگشت مشتری'];
        }
        $tabs[] = ['key' => 'discounts', 'label' => 'کدهای تخفیف'];
        if ($modules->isEnabled('reseller')) {
            $tabs[] = ['key' => 'resellers', 'label' => 'نمایندگان'];
            $tabs[] = ['key' => 'reseller_bots', 'label' => 'ربات‌های نماینده'];
            $tabs[] = ['key' => 'reseller_xui_panels', 'label' => 'پنل‌های نماینده'];
            $tabs[] = ['key' => 'reseller_charge', 'label' => 'شارژ نماینده'];
            $tabs[] = ['key' => 'reseller_settings', 'label' => 'تنظیمات نماینده'];
        }
        $tabs[] = ['key' => 'audit', 'label' => 'ممیزی'];

        return $tabs;
    }

    /**
     * @param  list<array{key: string, label: string}>  $tabs
     * @param  array<string, bool>  $allowedMap
     * @return list<array{key: string, label: string}>
     */
    public function filterForReseller(array $tabs, array $allowedMap): array
    {
        return array_values(array_filter(
            $tabs,
            fn (array $tab) => ! empty($allowedMap[$tab['key'] ?? ''])
        ));
    }
}
