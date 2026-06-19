<?php

namespace App\Providers;

use App\Services\AdminState\AdminActorResolver;
use App\Services\AdminState\AdminUserDetailBuilder;
use App\Services\AdminState\PanelHealthService;
use App\Services\AdminState\PaginationBuilder;
use App\Services\DashboardBootBuilder;
use App\Services\MutationRegistry;
use App\Services\NavTabsBuilder;
use App\Services\SettingsStore;
use App\Modules\Reseller\Services\ResellerScopeService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(MutationRegistry::class);
        $this->app->singleton(SettingsStore::class);
        $this->app->singleton(NavTabsBuilder::class);
        $this->app->singleton(DashboardBootBuilder::class);
        $this->app->singleton(ResellerScopeService::class);
        $this->app->singleton(AdminActorResolver::class);
        $this->app->singleton(PaginationBuilder::class);
        $this->app->singleton(PanelHealthService::class);
        $this->app->singleton(AdminUserDetailBuilder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->hydrateBotTokensFromEnv();
    }

    /** Seed empty DB settings from SVP_*_BOT_TOKEN env on boot (spec §9). */
    protected function hydrateBotTokensFromEnv(): void
    {
        if ($this->app->runningUnitTests()) {
            return;
        }

        try {
            $settings = $this->app->make(SettingsStore::class);
            foreach ([
                'SVP_TELEGRAM_BOT_TOKEN' => 'telegram_bot_token',
                'SVP_BALE_BOT_TOKEN' => 'bale_token',
            ] as $envKey => $settingKey) {
                $fromEnv = trim((string) env($envKey, ''));
                if ($fromEnv === '') {
                    continue;
                }
                if (trim((string) $settings->get($settingKey, '')) !== '') {
                    continue;
                }
                $settings->set($settingKey, $fromEnv);
            }
        } catch (\Throwable) {
            // DB may be unavailable during early bootstrap / migrate.
        }
    }
}
