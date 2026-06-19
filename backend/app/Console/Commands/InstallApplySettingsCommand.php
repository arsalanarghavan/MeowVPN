<?php

namespace App\Console\Commands;

use App\Services\SettingsStore;
use Illuminate\Console\Command;

class InstallApplySettingsCommand extends Command
{
    protected $signature = 'svp:install-apply-settings
        {--core-url= : Public API base URL (APP_URL)}
        {--dashboard-url= : Dashboard SPA public URL}
        {--telegram-url= : Telegram bot worker public base URL}
        {--bale-url= : Bale bot worker public base URL}
        {--relay-url= : Relay public URL}
        {--telegram-webhook-secret= : Path secret for Telegram webhook}
        {--bale-webhook-secret= : Path secret for Bale webhook}
        {--relay-shared-secret= : Shared secret for relay admin API}
        {--enable-relay : Enable telegram relay in settings}';

    protected $description = 'Apply MeowVPN install-time settings (domains, webhook secrets, relay)';

    public function handle(SettingsStore $settings): int
    {
        $core = rtrim((string) $this->option('core-url'), '/');
        if ($core === '') {
            $this->error('--core-url is required');

            return self::FAILURE;
        }

        $patch = [
            'public_site_url' => $core,
            'enabled' => true,
        ];

        $dashboard = rtrim((string) $this->option('dashboard-url'), '/');
        if ($dashboard !== '') {
            $patch['dashboard_public_url'] = $dashboard;
        }

        $telegram = rtrim((string) $this->option('telegram-url'), '/');
        if ($telegram !== '') {
            $patch['telegram_public_webhook_base'] = $telegram;
            $patch['telegram_relay_laravel_forward_url'] = $telegram;
        }

        $bale = rtrim((string) $this->option('bale-url'), '/');
        if ($bale !== '') {
            $patch['bale_public_webhook_base'] = $bale;
        }

        $relay = rtrim((string) $this->option('relay-url'), '/');
        if ($relay !== '') {
            $patch['telegram_relay_public_url'] = $relay;
            $patch['telegram_relay_admin_url'] = $relay;
        }

        if ($this->option('enable-relay')) {
            $patch['telegram_relay_enabled'] = true;
        }

        $tgSecret = (string) $this->option('telegram-webhook-secret');
        if ($tgSecret !== '') {
            $patch['telegram_webhook_secret'] = $tgSecret;
        }

        $baleSecret = (string) $this->option('bale-webhook-secret');
        if ($baleSecret !== '') {
            $patch['bale_webhook_secret'] = $baleSecret;
        }

        $relaySecret = (string) $this->option('relay-shared-secret');
        if ($relaySecret !== '') {
            $patch['telegram_relay_shared_secret'] = $relaySecret;
        }

        $settings->merge($patch);

        $this->info('Install settings applied: '.implode(', ', array_keys($patch)));

        return self::SUCCESS;
    }
}
