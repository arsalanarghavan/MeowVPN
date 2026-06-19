<?php

if (! function_exists('svp_module_env')) {
    /** @param bool $default */
    function svp_module_env(string $svpKey, string $legacyKey, bool $default): bool
    {
        $v = env($svpKey);
        if ($v !== null) {
            return filter_var($v, FILTER_VALIDATE_BOOLEAN);
        }
        $legacy = env($legacyKey);
        if ($legacy !== null) {
            return filter_var($legacy, FILTER_VALIDATE_BOOLEAN);
        }

        return $default;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | SimpleVPBot modules (enable/disable)
    |--------------------------------------------------------------------------
    | Set SVP_MODULE_* in .env (legacy alias MODULE_*_ENABLED).
    */
    'modules' => [
        'core' => [
            'enabled' => true,
            'label' => 'Core',
            'depends' => [],
            'provider' => \App\Modules\Core\CoreServiceProvider::class,
        ],
        'telegram' => [
            'enabled' => svp_module_env('SVP_MODULE_TELEGRAM', 'MODULE_TELEGRAM_ENABLED', true),
            'label' => 'Telegram Bot',
            'depends' => ['core'],
            'provider' => \App\Modules\Telegram\TelegramServiceProvider::class,
        ],
        'bale' => [
            'enabled' => svp_module_env('SVP_MODULE_BALE', 'MODULE_BALE_ENABLED', true),
            'label' => 'Bale Bot',
            'depends' => ['core'],
            'provider' => \App\Modules\Bale\BaleServiceProvider::class,
        ],
        'xui_panel' => [
            'enabled' => svp_module_env('SVP_MODULE_XUI_PANEL', 'MODULE_XUI_PANEL_ENABLED', true),
            'label' => '3x-ui Panel',
            'depends' => ['core'],
            'provider' => \App\Modules\XuiPanel\XuiPanelServiceProvider::class,
        ],
        'relay' => [
            'enabled' => svp_module_env('SVP_MODULE_RELAY', 'MODULE_RELAY_ENABLED', false),
            'label' => 'Telegram Relay',
            'depends' => ['core', 'telegram'],
            'provider' => \App\Modules\Relay\RelayServiceProvider::class,
        ],
        'crypto' => [
            'enabled' => svp_module_env('SVP_MODULE_CRYPTO', 'MODULE_CRYPTO_ENABLED', false),
            'label' => 'Crypto (NOWPayments)',
            'depends' => ['core'],
            'provider' => \App\Modules\Crypto\CryptoServiceProvider::class,
        ],
        'l2tp' => [
            'enabled' => svp_module_env('SVP_MODULE_L2TP', 'MODULE_L2TP_ENABLED', false),
            'label' => 'L2TP',
            'depends' => ['core'],
            'provider' => \App\Modules\L2tp\L2tpServiceProvider::class,
        ],
        'marketing' => [
            'enabled' => svp_module_env('SVP_MODULE_MARKETING', 'MODULE_MARKETING_ENABLED', true),
            'label' => 'Marketing',
            'depends' => ['core'],
            'provider' => \App\Modules\Marketing\MarketingServiceProvider::class,
        ],
        'reseller' => [
            'enabled' => svp_module_env('SVP_MODULE_RESELLER', 'MODULE_RESELLER_ENABLED', true),
            'label' => 'Reseller',
            'depends' => ['core'],
            'depends_any' => ['telegram', 'bale'],
            'provider' => \App\Modules\Reseller\ResellerServiceProvider::class,
        ],
        'backup' => [
            'enabled' => svp_module_env('SVP_MODULE_BACKUP', 'MODULE_BACKUP_ENABLED', true),
            'label' => 'Backup',
            'depends' => ['core'],
            'provider' => \App\Modules\Backup\BackupServiceProvider::class,
        ],
    ],
];
