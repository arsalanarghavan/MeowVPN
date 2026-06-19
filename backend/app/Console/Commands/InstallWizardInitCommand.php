<?php

namespace App\Console\Commands;

use App\Services\InstallWizard\InstallWizardService;
use App\Services\SettingsStore;
use Illuminate\Console\Command;

class InstallWizardInitCommand extends Command
{
    protected $signature = 'svp:install-wizard-init
        {--token= : One-time setup token (required)}
        {--core-url= : Public API URL}
        {--dashboard-url= : Dashboard SPA URL}
        {--telegram-url= : Telegram worker URL}
        {--bale-url= : Bale worker URL}
        {--relay-url= : Relay public URL}';

    protected $description = 'Enable post-install setup wizard with a one-time token';

    public function handle(InstallWizardService $wizard, SettingsStore $settings): int
    {
        $token = trim((string) $this->option('token'));
        if ($token === '') {
            $this->error('--token is required');

            return self::FAILURE;
        }

        $snapshot = [
            'core_url' => rtrim((string) $this->option('core-url'), '/'),
            'dashboard_url' => rtrim((string) $this->option('dashboard-url'), '/'),
            'telegram_url' => rtrim((string) $this->option('telegram-url'), '/'),
            'bale_url' => rtrim((string) $this->option('bale-url'), '/'),
            'relay_url' => rtrim((string) $this->option('relay-url'), '/'),
        ];

        if ($snapshot['core_url'] === '') {
            $snapshot['core_url'] = rtrim((string) $settings->get('public_site_url', ''), '/');
        }
        if ($snapshot['dashboard_url'] === '') {
            $snapshot['dashboard_url'] = rtrim((string) $settings->get('dashboard_public_url', ''), '/');
        }
        if ($snapshot['telegram_url'] === '') {
            $snapshot['telegram_url'] = rtrim((string) $settings->get('telegram_public_webhook_base', ''), '/');
        }
        if ($snapshot['bale_url'] === '') {
            $snapshot['bale_url'] = rtrim((string) $settings->get('bale_public_webhook_base', ''), '/');
        }
        if ($snapshot['relay_url'] === '') {
            $snapshot['relay_url'] = rtrim((string) $settings->get('telegram_relay_public_url', ''), '/');
        }

        $wizard->init($token, $snapshot);
        $this->info('Install wizard enabled.');

        return self::SUCCESS;
    }
}
